<?php

namespace MobileStock\MakeBatchingRoutes\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use MobileStock\MakeBatchingRoutes\Enum\OrderByEnum;
use MobileStock\MakeBatchingRoutes\Services\RequestService;

class Batching
{
    public function find(RequestService $service)
    {
        $requestData = Request::except(['limit', 'page', 'order_by_field', 'order_by_direction', 'without_scopes']);
        $configs = Request::validate([
            'limit' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'page' => ['nullable', 'integer', 'min:1'],
            'order_by_field' => ['nullable', 'string'],
            'order_by_direction' => ['nullable', Rule::enum(\MobileStock\MakeBatchingRoutes\Enum\OrderByEnum::class)],
            'without_scopes' => ['nullable', 'boolean'],
        ]);

        $limit = $configs['limit'] ?? 1000;
        $page = $configs['page'] ?? 1;
        $offset = $limit * ($page - 1);

        /**  @var \Illuminate\Database\Eloquent\Model $model*/
        $model = $service->getRouteModel(false);
        $query = $model::query()->limit($limit)->offset($offset);

        if (!empty($configs['without_scopes'])) {
            $hasPermissionToIgnoreScopes = $service->shouldIgnoreModelScopes($model);
            if ($hasPermissionToIgnoreScopes) {
                $query->withoutGlobalScopes();
            }
        }

        $table = $model->getTable();
        $columns = Schema::getColumnListing($table);
        $orderKey = $configs['order_by_field'] ?? (array_key_first($requestData) ?? current($columns));
        if (!in_array($orderKey, $columns)) {
            throw new InvalidArgumentException(
                "O campo order_by_field '$orderKey' não é uma coluna válida na tabela '$table'."
            );
        }

        $direction = match (true) {
            !empty($configs['order_by_direction']) => OrderByEnum::from($configs['order_by_direction']),
            !empty($requestData) => OrderByEnum::CUSTOM,
            default => OrderByEnum::ASC,
        };

        if ($direction === OrderByEnum::CUSTOM) {
            if (!isset($requestData[$orderKey])) {
                throw new InvalidArgumentException(
                    'Para utilizar a ordenação customizada, é necessário que seja enviado também os valores de filtragem'
                );
            }

            $sorter = $requestData[$orderKey];
            $bindings = array_fill(0, count($sorter), '?');
            $placeholders = implode(', ', $bindings);

            $query->orderByRaw("FIELD($orderKey, $placeholders)", $sorter);
        } else {
            $query->orderBy($orderKey, $direction->value);
        }

        foreach ($requestData as $key => $value) {
            $query->whereIn($key, $value);
        }

        $databaseValues = $query->get()->toArray();

        return $databaseValues;
    }

    public function findGrouped(RequestService $service)
    {
        /**  @var \Illuminate\Database\Eloquent\Model $model*/
        $model = $service->getRouteModel(true);
        $query = $model::query();

        $hasPermissionToIgnoreScopes = $service->shouldIgnoreModelScopes($model);
        if ($hasPermissionToIgnoreScopes) {
            $query->withoutGlobalScopes();
        }

        $requestData = Request::all();

        foreach ($requestData as $key => $value) {
            $query->whereIn($key, $value);
        }

        $key = array_key_first($requestData);
        $databaseValues = $query->get();
        $databaseValues = $databaseValues->groupBy($key);
        $sorter = $requestData[$key];
        $databaseValues = Collection::make($sorter)->mapWithKeys(
            fn(string $key): array => [$key => $databaseValues->get($key, Collection::make())]
        );
        $databaseValues = $databaseValues->sortKeysUsing(function (mixed $a, mixed $b) use ($sorter): int {
            $indexA = array_search($a, $sorter);
            $indexB = array_search($b, $sorter);
            return $indexA <=> $indexB;
        });
        $databaseValues = $databaseValues->values();
        $databaseValues = $databaseValues->toArray();

        return $databaseValues;
    }
}

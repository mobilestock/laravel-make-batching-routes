<?php

namespace MobileStock\MakeBatchingRoutes\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use MobileStock\MakeBatchingRoutes\Enum\OrderByEnum;

class Batching
{
    // @issue: https://github.com/mobilestock/backend/issues/1294
    public function find()
    {
        $model = Request::batchingRouteModel();
        $requestData = Request::except(['limit', 'page', 'order_by_field', 'order_by_direction']);
        $configs = Request::validate([
            'limit' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'page' => ['nullable', 'integer', 'min:1'],
            'order_by_field' => ['nullable', 'string'],
            'order_by_direction' => ['nullable', Rule::enum(\MobileStock\MakeBatchingRoutes\Enum\OrderByEnum::class)],
        ]);

        $limit = $configs['limit'] ?? 1000;
        $page = $configs['page'] ?? 1;
        $offset = $limit * ($page - 1);

        /**  @var \Illuminate\Database\Eloquent\Model $model*/
        $query = $model::query()->limit($limit)->offset($offset);
        if (empty($requestData)) {
            $table = $model->getTable();
            $columns = Schema::getColumnListing($table);
            $order = $configs['order_by_field'] ?? current($columns);
            $direction = $configs['order_by_direction'] ?? OrderByEnum::ASC->value;

            $query->orderBy($order, $direction);
        }

        $shouldIgnoreScope = Request::header('X-Ignore-Scopes');
        if (!empty($shouldIgnoreScope)) {
            $permissions = $model::getBatchingGlobalAccessPermissions();

            $hasPermissionToIgnoreScopes = $permissions->some(function (string $permission): bool {
                Auth::shouldUse($permission);
                $allowed = Gate::allows($permission);

                return $allowed;
            });

            if ($hasPermissionToIgnoreScopes) {
                $query->withoutGlobalScopes();
            }
        }

        foreach ($requestData as $key => $value) {
            $query->whereIn($key, $value);
        }

        $databaseValues = $query->get()->toArray();
        if (empty($requestData) || empty($configs['order_by_field'])) {
            return $databaseValues;
        }

        $key = $configs['order_by_field'];
        $sorter = $requestData[$key];
        usort($databaseValues, function (array $a, array $b) use ($key, $sorter): int {
            $indexA = array_search($a[$key], $sorter);
            $indexB = array_search($b[$key], $sorter);
            return $indexA <=> $indexB;
        });

        return $databaseValues;
    }

    // @issue: https://github.com/mobilestock/backend/issues/1294
    public function findGrouped()
    {
        $model = Request::batchingRouteModel();
        $query = $model::query();

        $shouldIgnoreScope = Request::header('X-Ignore-Scopes');
        if (!empty($shouldIgnoreScope)) {
            $permissions = $model::getBatchingGlobalAccessPermissions();

            $hasPermissionToIgnoreScopes = $permissions->some(function (string $permission): bool {
                Auth::shouldUse($permission);
                $allowed = Gate::allows($permission);

                return $allowed;
            });

            if ($hasPermissionToIgnoreScopes) {
                $query->withoutGlobalScopes();
            }
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

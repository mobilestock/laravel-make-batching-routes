<?php

namespace MobileStock\MakeBatchingRoutes\Enum;

enum OrderByEnum: string
{
    case ASC = 'ASC';
    case DESC = 'DESC';
    case CUSTOM = 'CUSTOM';
}

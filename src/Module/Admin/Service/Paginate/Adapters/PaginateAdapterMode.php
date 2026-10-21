<?php

namespace App\Module\Admin\Service\Paginate\Adapters;

enum PaginateAdapterMode : int{
        case CursorMode = 0;
        case OffsetMode = 1;
}
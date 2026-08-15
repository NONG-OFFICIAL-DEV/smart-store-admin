<?php

namespace App\Repositories\Eloquent;

use App\Models\Menu;
use App\Repositories\Contracts\MenuRepositoryInterface;

class MenuRepository extends BaseRepository implements MenuRepositoryInterface
{
    protected array $searchable = ['name'];

    public function __construct(Menu $model)
    {
        parent::__construct($model);
    }
}

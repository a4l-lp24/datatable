<?php

namespace App\Http\Resources\Traits;

use App\Http\Resources\DataTableMops\DataTable AS DataTableDataTable;
use Illuminate\Http\Request;

trait DataTable {
    public static function watchDataTable($data, Request $request, array $schema = [], array $with = [], bool $collection = true): mixed {
        $table = new DataTableDataTable($data, $request, $with, $schema);

        return $collection ? $table->get() : $table->getData();
    }
}
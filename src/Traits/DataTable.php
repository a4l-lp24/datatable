<?php

namespace App\Http\Resources\Traits;

use Illuminate\Http\Request;
use App\Http\Resources\DataTableMops\DataTable AS DataTableDataTable;

trait DataTable
{


    /**
     * Display a listing of the resource.
     *
     * @param $data
     * @param Request $request
     * @param array $schema
     * @param array $with
     * @param bool $collection
     * @return mixed
     */
    public static function watchDataTable($data, Request $request, array $schema = [], $with = [], $collection = true): mixed
    {

        $dt = new DataTableDataTable($data, $request, $with, $schema);
        return $collection ? $dt->get() : $dt->getData();

    }

}

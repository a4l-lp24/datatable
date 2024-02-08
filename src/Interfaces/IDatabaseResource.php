<?php

namespace App\Http\Resources\DataTable\Interfaces;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model AS EloquentModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Http\Resources\DataTable\Abstracts\DatabaseResource;

interface IDatabaseResource{

    /**
     * Initialize data.
     *
     * @return DatabaseResource
     */
    public function init(EloquentModel|Builder|Relation $model, Request $request, $with = [], $schema = []): DatabaseResource;
    /**
     * Display the resource.
     *
     * @return EloquentModel|Builder
     */
    public function getBuilder(): EloquentModel|Builder|Relation;

    /**
     * Display listing of the resource.
     *
     * @return LengthAwarePaginator
     */
    public function getData(): LengthAwarePaginator;

}

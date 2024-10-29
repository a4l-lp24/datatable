<?php

namespace DataTable\Interfaces;

use DataTable\Abstracts\DatabaseResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

interface IDatabaseResource{
    public function init(Model|Builder|Relation $model, Request $request, array $with = [], array $schema = []): DatabaseResource;

    public function getBuilder(): Model|Builder|Relation;

    public function getData(): LengthAwarePaginator;
}
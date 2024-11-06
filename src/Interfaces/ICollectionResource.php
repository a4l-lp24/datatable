<?php

namespace DataTable\Interfaces;

use DataTable\Abstracts\CollectionResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface ICollectionResource {
    public function init(Collection $collection, Request $request, array $with = []): CollectionResource;

    public function getBuilder(): Collection;

    public function getData(): LengthAwarePaginator;
}
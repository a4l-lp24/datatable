<?php

namespace DataTable\Interfaces;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use \Illuminate\Support\Collection;
use DataTable\Abstracts\CollectionResource;

interface ICollectionResource{

    /**
     * Initialize data.
     *
     * @return CollectionResource
     */
    public function init(Collection $collection, Request $request, $with = []): CollectionResource;
    /**
     * Display the resource.
     *
     * @return Collection
     */
    public function getBuilder(): Collection;

    /**
     * Display listing of the resource.
     *
     * @return LengthAwarePaginator
     */
    public function getData(): LengthAwarePaginator;

}

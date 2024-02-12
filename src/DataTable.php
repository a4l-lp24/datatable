<?php

namespace DataTable;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use \Illuminate\Support\Collection AS SupportCollection;
use \Illuminate\Database\Eloquent\Model AS EloquentModel;
use \Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use DataTable\Interfaces\ICollectionResource;
use DataTable\Interfaces\IDatabaseResource;

class DataTable
{

    protected SupportCollection|EloquentModel|Builder|Relation $data;
    protected Request $request;
    protected array $with = [];
    protected ICollectionResource|IDatabaseResource $resource;

    public function __construct(SupportCollection|EloquentModel|Builder|Relation $data, Request $request, array $with = [])
    {

        $this->data = $data;
        $this->request = $request;
        $this->with = $with;

        if ($this->data instanceof SupportCollection) {
            $resource = config("datatable.resources.collection");
        } elseif (!empty($this->request->cols[0]["key"])) {
            $resource = config("datatable.resources.grid");
        } else{
            $resource = config("datatable.resources.api");
        }
        $this->setResource(new $resource);
    }

    /**
     * Display listing of the resource.
     *
     * @return DataTable
     */
    public function setResource(ICollectionResource|IDatabaseResource $resource): DataTable
    {

        $this->resource = $resource->init($this->data, $this->request, $this->with);
        return $this;

    }

    /**
     * Display listing of the resource.
     *
     * @return mixed
     */
    public function getBuilder(): mixed
    {

        return $this->resource->getBuilder();

    }

    /**
     * Display a listing of the resource in GeneralCollection.
     *
     * @return LengthAwarePaginator
     */
    public function getData(): LengthAwarePaginator
    {

        return $this->resource->getData();

    }

    /**
     * Display a listing of the resource in Collection set in config file.
     *
     * @return mixed
     */
    public function get(): mixed
    {

        $collection = config("datatable.collection");
        return new $collection($this->resource->getData());

    }

    /**
     * Display query of resource in debug dd
     *
     * @return never
     */
    public function dd(): never
    {

        DB::enableQueryLog();
        $this->resource->getData();
        dd(DB::getQueryLog());

    }

}

<?php

namespace DataTable;

use DataTable\Interfaces\ICollectionResource;
use DataTable\Interfaces\IDatabaseResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

class DataTable {
    protected ICollectionResource|IDatabaseResource $resource;
    protected Collection|Model|Builder|Relation $data;
    protected Request $request;
    protected array $with = [];

    public function __construct(Collection|Model|Builder|Relation $data, Request $request, array $with = []) {
        $this->data = $data;
        $this->request = $request;
        $this->with = $with;

        if ($this->data instanceof Collection) {
            $resource = config('datatable.resources.collection');
        } elseif (!empty($this->request->cols[0]['key'])) {
            $resource = config('datatable.resources.grid');
        } else {
            $resource = config('datatable.resources.api');
        }

        $this->resource = (new $resource)->init($this->data, $this->request, $this->with, $this->schema);
    }

    public function getBuilder(): mixed { # Display listing of the resource
        return $this->resource->getBuilder();
    }

    public function getData(): LengthAwarePaginator { # Display a listing of the resource in GeneralCollection
        return $this->resource->getData();
    }

    public function get(): mixed { # Display a listing of the resource in Collection set in config file
        $collection = config('datatable.collection');

        return new $collection($this->resource->getData());
    }

    public function dd(): never { # Display query of resource in debug dd
        DB::enableQueryLog();

        $this->resource->getData();

        dd(DB::getQueryLog());
    }
}
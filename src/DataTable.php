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
        # autocomplete in column search: unique values of one column in the current view
        if (!empty($this->request->distinct))
            return ['data' => $this->getDistinctValues($this->request->distinct)];

        $collection = config('datatable.collection');

        return new $collection($this->resource->getData());
    }

    public function getDistinctValues(string $column): array { # Unique values of one column with all filters of the table, without paging
        # only a column which the table itself sends as searchable
        $requested = collect($this->request->cols ?? [])->first(function ($col) use ($column) {
            return ($col['text'] ?? $col['data'] ?? $col['key'] ?? null) === $column;
        });

        if (($requested['searchable'] ?? null) !== 'true')
            abort(422, 'Column ' . $column . ' is not searchable');

        # copy of the table query with all its filters
        $builder = clone $this->resource->getBuilder();

        # e.g. job.pricelist.purchasegroup.itemgroup.producttype.name -> join relations, column producttype.name
        $relations = explode('.', $column);
        $field = array_pop($relations);
        $model = $builder->getModel();

        foreach ($relations as $relation)
            $model = $model->$relation()->getRelated();

        if (in_array($field, $model->getHidden()))
            abort(422, 'Column ' . $column . ' is hidden');

        if ($relations)
            $builder->joinRelations(implode('.', $relations));

        $valueColumn = $model->getTable() . '.' . $field;

        # remove paging, sorting and grouping of the table
        $query = $builder->toBase();
        $query->columns = null;
        $query->groups = null;
        $query->orders = null;
        $query->bindings['select'] = [];
        $query->bindings['order'] = [];

        $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'CAST(' . $valueColumn . ' AS TEXT) ILIKE ?' : 'CAST(' . $valueColumn . ' AS CHAR) LIKE ?';

        return $query
            ->select($valueColumn . ' as value')
            ->distinct()
            ->whereNotNull($valueColumn)
            ->whereRaw($like, ['%' . ($requested['search'] ?? '') . '%'])
            ->orderBy('value', $this->request->orderDirection === 'desc' ? 'desc' : 'asc')
            ->limit(min((int) ($this->request->per_page ?: 10), 200))
            ->pluck('value')
            ->map(fn ($value) => ['value' => $value])
            ->all();
    }

    public function dd(): never { # Display query of resource in debug dd
        DB::enableQueryLog();

        $this->resource->getData();

        dd(DB::getQueryLog());
    }
}
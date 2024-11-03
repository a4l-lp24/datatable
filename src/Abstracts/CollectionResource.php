<?php

namespace DataTable\Abstracts;

use DataTable\Interfaces\ICollectionResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

abstract class CollectionResource implements ICollectionResource {
    protected Collection $collection;
    protected Request $request;
    protected string $table_name;
    protected array $with = [];
    protected array $selectColumns;
    protected array $table_columns;
    protected array $columns;
    protected array $select = ['*'];
    protected int $per_page = 50;
    protected bool $strict_with_mode = true;
    protected const PAGE_NAME = 'page';
    protected const PER_PAGE_NAME = 'per_page';
    protected const SELECT_COLUMN_NAME = 'select';
    protected const ORDER_COLUMN_NAME = 'orderColumn';
    protected const ORDER_DIRECTION_NAME = 'orderDirection';

    protected const PREVENTED_COLUMN_NAMES = [
        self::ORDER_COLUMN_NAME,
        self::ORDER_DIRECTION_NAME,
        self::SELECT_COLUMN_NAME,
        self::PAGE_NAME,
        self::PER_PAGE_NAME
    ];

    public function init(Collection $collection, Request $request, array $with = []): CollectionResource {
        $this->collection = $collection;
        $this->request = $request;
        $this->with = $with;
        $this->per_page = config('datatable.default_per_page', 50);
        $this->strict_with_mode = config('datatable.strict_with_mode', true);

        $this->build();

        return $this;
    }

    public function getBuilder(): Collection {
        return $this->collection;
    }

    public function getData(): LengthAwarePaginator {
        $perPage = (!empty($this->request->{self::PER_PAGE_NAME}) && is_numeric($this->request->{self::PER_PAGE_NAME})) ? $this->request->{self::PER_PAGE_NAME} : $this->per_page;
        $page = $this->request->{self::PAGE_NAME};

        return new LengthAwarePaginator(
            $this->collection->forPage($page, $perPage)->values(),
            $this->collection->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => self::PAGE_NAME
            ]
        );
    }

    abstract protected function build(): CollectionResource;
}
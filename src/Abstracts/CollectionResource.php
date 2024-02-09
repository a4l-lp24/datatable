<?php

/**
 * Searching in collections
 *
 */

namespace DataTable\Abstracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use \Illuminate\Support\Collection;
use DataTable\Interfaces\ICollectionResource;

class CollectionResource implements ICollectionResource
{

    protected Collection $collection;
    protected Request $request;
    protected array $with = [];
    protected array $selectColumns;
    protected string $table_name;
    protected array $table_columns;
    protected array $columns;
    protected array $select = ["*"];
    protected const PER_PAGE = 20;
    protected const PAGE_NAME = "page";
    protected const PER_PAGE_NAME = "per_page";
    protected const SELECT_COLUMN_NAME = "select";
    protected const ORDER_COLUMN_NAME = "orderColumn";
    protected const ORDER_DIRECTION_NAME = "orderDirection";
    protected bool $strict_with_mode = true;
    protected const PREVENTED_COLUMN_NAMES = [self::PER_PAGE, self::ORDER_COLUMN_NAME, self::ORDER_DIRECTION_NAME, self::SELECT_COLUMN_NAME, self::PAGE_NAME, self::PER_PAGE_NAME];

    public function init(Collection $collection, Request $request, $with = []): CollectionResource
    {

        $this->collection = $collection;
        $this->request = $request;
        $this->with = $with;
        $this->strict_with_mode = config("core.datatable.strict_with_mode", true);
        $this->build();

        return $this;

    }

    protected function build(): CollectionResource
    {

        return $this;

    }

    public function getData(): LengthAwarePaginator
    {

        return $this->paginate();

    }

    private function paginate(): LengthAwarePaginator
    {

        $perPage = (!empty($this->request->{self::PER_PAGE_NAME}) and is_numeric($this->request->{self::PER_PAGE_NAME})) ? $this->request->{self::PER_PAGE_NAME} : self::PER_PAGE;
        $page = $this->request->{self::PAGE_NAME};

        return new LengthAwarePaginator(
            $this->collection->forPage($page, $perPage),
            $this->collection->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => self::PAGE_NAME,
            ]
        );
    }

    public function getBuilder(): Collection
    {

        return $this->collection;

    }

}

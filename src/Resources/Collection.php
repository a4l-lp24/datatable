<?php

/**
 * Searching in collections
 *
 */

namespace DataTable\Resources;

use DataTable\Abstracts\CollectionResource;

class Collection extends CollectionResource
{

    protected function build(): Collection
    {

        $collection = $this->collection;

        // ensure we have array of arrays
        $collection->transform(function ($item) {
            return (array) $item;
        });

        // filter columns and remove undefined
        $columns = $this->request->cols ?? $this->request->columns ?? [];

        // select only specific columns
        //if (!empty($columns)) $collection = $collection->only(array_column($columns, "key"));

        // search all the columns
        if (!empty($this->request->search) and $columns) {

            $searchString = $this->request->search;
            $collection = $collection->filter(function ($item) use ($searchString, $columns) {
                foreach ($columns as $column) {
                    return stripos($item[$column["key"]], $searchString) !== false;
                }
            });

            // search by column
        } else {

            foreach ($columns as $column) {

                if (!empty($column["search"])) {
                    $collection = $collection->filter(function ($item) use ($column) {
                        return stripos($item[$column["key"]], $column["search"]) !== false;
                    });
                }
            };
        }

        // search by default preconfigured params
        if (!empty($this->request->params)) {

            foreach ($this->request->params as $key => $column) {

                $collection = $collection->filter(function ($item) use ($column, $key) {
                    return array_key_exists($key, $item) ? (stripos($item[$key], $column) !== false) : true;
                });

            }
        }

        $orderColumn = $this->request->{self::ORDER_COLUMN_NAME};
        $orderDirection = $this->request->{self::ORDER_DIRECTION_NAME} ?? "asc";

        // sort
        if (!empty($orderColumn) and !empty($orderDirection) and in_array($orderDirection, ["desc", "asc"])) {
            $collection = $orderDirection === "desc" ? $collection->sortByDesc($orderColumn) : $collection->sortBy($orderColumn);
        }

        $this->collection = $collection;

        return $this;

    }

}

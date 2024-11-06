<?php

namespace DataTable\Resources;

use DataTable\Abstracts\CollectionResource;

# Searching in collections

class Collection extends CollectionResource {
    protected function build(): Collection {
        $collection = $this->collection;

        $collection->transform(function ($item) {
            return (array) $item;
        });

        $columns = $this->request->cols ?? $this->request->columns ?? [];
        $search = $this->request->search ?? '';
        $params = $this->request->params ?? [];

        if (!empty($search) && !empty($columns))
            $collection = $collection->filter(function ($item) use ($search, $columns) {
                foreach ($columns as $column)
                    return stripos($item[$column['key']], $search) !== false;
            });
        else
            foreach ($columns as $column)
                if (!empty($column['search']))
                    $collection = $collection->filter(function ($item) use ($column) {
                        return stripos($item[$column['key']], $column['search']) !== false;
                    });

        foreach ($params as $key => $column)
            $collection = $collection->filter(function ($item) use ($column, $key) {
                return array_key_exists($key, $item) ? (stripos($item[$key], $column) !== false) : true;
            });

        $orderColumn = $this->request->{self::ORDER_COLUMN_NAME};
        $orderDirection = $this->request->{self::ORDER_DIRECTION_NAME} ?? 'asc';

        if (!empty($orderColumn) && !empty($orderDirection) && in_array($orderDirection, ['desc', 'asc']))
            $collection = $orderDirection === 'desc' ? $collection->sortByDesc($orderColumn) : $collection->sortBy($orderColumn);

        $this->collection = $collection;

        return $this;
    }
}
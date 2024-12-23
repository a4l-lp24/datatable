<?php

namespace DataTable\Resources;

use DataTable\Abstracts\DatabaseResource;

# Searching with vue datatable controller

class Grid extends DatabaseResource {
    protected function build(): DatabaseResource {
        $this->filterColumns($this->request->cols ?: []);

        $this->setSelect($this->selectColumns ?: ['*']);

        $this->setWith($this->selectWiths);

        if (!empty($this->request->search)) {
            foreach ($this->columns as $column)
                if (!empty($column['searchable']))
                    $this->model = $this->resolveColumnSearchString($this->model, $column['text'] ?? $column['data'] ?? $column['key'], $this->request->search, 'or');
        } else {
            foreach ($this->columns as $column)
                if (!empty($column['search']))
                    $this->model = $this->resolveColumnSearchString($this->model, $column['text'] ?? $column['data'] ?? $column['key'], $column['search']);
        }

        foreach ($this->request->params ?? [] as $key => $column) {
            if (str_contains($key, 'or_') && is_array($column))
                $this->model = $this->model->where(function ($query) use ($column) {
                    $query->where(function ($orQuery) use ($column) {
                        foreach ($column as $index => $record)
                            foreach ($record as $subKey => $subColumn)
                                $orQuery = $this->resolveColumnSearchString($orQuery, $subKey, $subColumn, $index === 0 ? 'and' : 'or');
                    });
                });
            else
                $this->model = $this->model->where(function () use ($column, $key) {
                    $this->model = $this->resolveColumnSearchString($this->model, $key, $column);
                });
        }

        $this->sortData();

        return $this;
    }
}
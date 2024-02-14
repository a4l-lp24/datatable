<?php

/**
 * Searching with vue datatable controller
 *
 */

namespace DataTable\Resources;

use DataTable\Abstracts\DatabaseResource;

class Grid extends DatabaseResource
{

    public function build(): DatabaseResource
    {

        // filter columns and remove undefined
        $this->filterColumns($this->request->cols ?: []);

        // select only specific relations in current model
        $this->setWith($this->selectWiths);

        // search all the columns
        if (!empty($this->request->search)) {

            $searchString = $this->request->search;
            foreach ($this->columns as $column) {
                if (!empty($column["searchable"])) $this->model = $this->resolveColumnSearchString($this->model, ($column["text"] ?? $column["data"] ?? $column["key"]), $searchString, "or");
            }

            // search by column
        } elseif ($this->columns) {

            foreach ($this->columns as $key => $column) {
                if (!empty($column["search"])) $this->model = $this->resolveColumnSearchString($this->model, ($column["text"] ?? $column["data"] ?? $column["key"]), $column["search"]);
            }
        }


        // search by default preconfigured params
        if (!empty($this->request->params)) {
            foreach ($this->request->params as $key => $column) {
                $this->model = $this->model->where(function () use ($column, $key) {
                    $this->model = $this->resolveColumnSearchString($this->model, $key, $column);
                });
            }
        }
 
        // select only specific columns in current model
        $this->setSelect($this->selectColumns ?: ["*"]);
        
        // sort data
        $this->sortData();

        return $this;
    }
}

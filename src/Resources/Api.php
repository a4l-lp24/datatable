<?php

/**
 * Searching like /data/purchases?name=xxx
 *
 */

namespace DataTable\Resources;

use DataTable\Abstracts\DatabaseResource;

class Api extends DatabaseResource
{

    public function build(): DatabaseResource
    {

        // filter columns and remove undefined
        $this->filterColumns($this->request->searchColumns ?: $this->request->all());

        // select only specific columns in current model
        $this->setSelect($this->request->select ?: ["*"]);

        // select only specific relations in current model      
        $this->setWith($this->request->with ?? $this->with);

        // search all the columns
        if (!empty($this->request->search)) {

            $searchString = $this->request->search;
            foreach ($this->table_columns as $column) {
                if (in_array($column, self::PREVENTED_COLUMN_NAMES)) continue; // hotfix to dont search in page field
                if (!empty($column)) $this->model = $this->resolveColumnSearchString($this->model, $column, $searchString, "or");
            }

            // search by column
        } elseif ($this->columns) {

            foreach ($this->columns as $key => $column) {
                if (in_array($column, self::PREVENTED_COLUMN_NAMES)) continue; // hotfix to dont search in page field
                $this->model = $this->resolveColumnSearchString($this->model, $key, $column);
            }
        }

        // sort data
        $this->sortData();

        return $this;

    }
}

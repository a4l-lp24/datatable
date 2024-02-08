<?php

/**
 * Searching like /data/purchases?name=xxx
 *
 */

namespace App\Http\Resources\DataTable\Resources;

use App\Http\Resources\DataTable\Abstracts\DatabaseResource;

class Api extends DatabaseResource
{

    protected const PER_PAGE = 50;

    public function build(): DatabaseResource
    {

        // filter columns and remove undefined
        $this->filterColumns($this->request->searchColumns ?: $this->request->all());

        // select only specific relations in current model      
        $this->setWith($this->with);

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



        // select only specific columns in current model
        $this->setSelect($this->request->select ?: ["*"]);
        $this->sortData();

        return $this;

    }
}

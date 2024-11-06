<?php

namespace DataTable\Resources;

use DataTable\Abstracts\DatabaseResource;

# Searching like /data/purchases?name=xxx

class Api extends DatabaseResource {
    protected function build(): DatabaseResource {
        $this->filterColumns($this->request->searchColumns ?: $this->request->all());

        $this->setWith($this->request->with ?? $this->with);

        if (!empty($this->request->search)) {
            foreach ($this->table_columns as $column) {
                if (in_array($column, self::PREVENTED_COLUMN_NAMES)) # hotfix to dont search in page field
                    continue;

                if (!empty($column))
                    $this->model = $this->resolveColumnSearchString($this->model, $column, $this->request->search, 'or');
            }
        } else {
            foreach ($this->columns as $key => $column) {
                if (in_array($column, self::PREVENTED_COLUMN_NAMES)) # hotfix to dont search in page field
                    continue;

                $this->model = $this->resolveColumnSearchString($this->model, $key, $column);
            }
        }

        $this->setSelect($this->request->select ?: ['*']);

        $this->sortData();

        return $this;
    }
}
<?php

namespace DataTable\Abstracts;

use DataTable\Interfaces\IDatabaseResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;

abstract class DatabaseResource implements IDatabaseResource {
    protected Model|Builder|Relation $model;
    protected Request $request;
    protected array $with = [];
    protected array $schema = [];
    protected array $selectColumns = [];
    protected array $selectWiths = [];
    protected string $table_name;
    protected array $table_columns = [];
    protected array $columns = [];
    protected array $select = ["*"];
    protected array $columnTypes = [];
    protected int $per_page = 50;
    protected bool $strict_with_mode = true;
    protected string|null $driver = null;
    protected const PGSQL_DRIVER = 'pgsql';
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

    protected const NUMERIC_TYPES = [
        'integer',
        'smallint',
        'bigint',
        'float',
        'decimal'
    ];

    public function init(Model|Builder|Relation $model, Request $request, array $with = [], array $schema = []): DatabaseResource {
        $this->model = $model;
        $this->request = $request;
        $this->with = $with;
        $this->schema = $schema;
        $this->table_name = $this->model->getModel()->getTable();
        $this->table_columns = $this->model->getConnection()->getSchemaBuilder()->getColumnListing($this->table_name);
        $this->strict_with_mode = config('datatable.strict_with_mode', true);
        $this->per_page = config('datatable.default_per_page', 50);
        $this->driver = DB::connection()->getDriverName();

        $this->build();

        return $this;
    }

    public function getBuilder(): Model|Builder {
        return $this->model;
    }

    public function getData(): LengthAwarePaginator {
        return $this->model->paginate(is_numeric($this->request->{self::PER_PAGE_NAME} ?? null) ? $this->request->{self::PER_PAGE_NAME} : $this->per_page, $this->select);
    }

    abstract protected function build(): DatabaseResource;

    # ----------

    protected function filterColumns(array $columns): void {
        $this->setColumns($columns);

        foreach ($this->columns as $kcol => $column) {
            if (is_array($column)) {
                $keys = [
                    $column['data'] ?? $column['key'] ?? $kcol
                ];

                if (!empty($column['text']))
                    $keys[] = $column['text'];    
            } else
                $keys = [$kcol];

            foreach ($keys as $key) {
                $ex = explode('.', $key);

                if (count($ex) === 1 && in_array($ex[0], $this->table_columns) && !in_array($this->table_name . '.' . $ex[0], $this->selectColumns)) 
                    $this->selectColumns[] = $this->table_name . '.' . $ex[0];

                if (count($ex) !== 1) {
                    $relationKey = implode('.', array_slice($ex, 0, count($ex) - 1));

                    if (in_array($relationKey, $this->selectWiths) || !$this->checkRelationPermissions($relationKey))
                        continue;

                    $this->selectWiths[] = $relationKey;

                    if (!method_exists($this->model->getModel(), $ex[0]))
                        continue;

                    $this->model->getModel()->setAttribute($ex[0], null);

                    $relation = $this->model->getModel()->{$ex[0]}();

                    $method = method_exists($relation, 'getLocalKeyName') ? 'getLocalKeyName' : (method_exists($relation, 'getParentKeyName') ? 'getParentKeyName' : 'getForeignKeyName');

                    $selectTableName = $this->table_name . '.' . $relation->{$method}();

                    if (!in_array($selectTableName, $this->selectColumns))
                        $this->selectColumns[] = $selectTableName;
                }
            }
        }
    }

    protected function checkRelationPermissions(string $key): bool {
        if (!$this->strict_with_mode)
            return true;

        foreach ($this->with as $with) {
            if (!is_array($with) && $with === $key)
                return true;

            if (is_array($with) && !empty($with['relation']) && $with['relation'] === $key && (empty($with['permissions']) || (Auth::user() && Auth::user()->Auth::user()->hasAnyPermission($with['permissions']))))
                return true;
        }

        return false;
    }

    protected function setWith(array $with): void {
        $this->with = $with;
        $this->model = $this->model->with($with);
    }

    protected function setColumns(array $columns): void {
        $this->columns = array_filter($columns, function($key) {
            return !in_array($key, self::PREVENTED_COLUMN_NAMES);
        }, ARRAY_FILTER_USE_KEY);
    }

    protected function setSelect(array $select): void {
        $this->select = $select;
    }

    protected function sortData(): void {
        $sortColumns = is_array($temp = $this->request->{self::ORDER_COLUMN_NAME} ?? null) ? $temp : [$temp];
        $orderColumns = is_array($temp = $this->request->{self::ORDER_DIRECTION_NAME} ?? null) ? $temp : [$temp];

        foreach (array_filter($sortColumns) as $key => $sort) {
            $ex = explode('.', $sort);
            $sort_method = count($ex) > 1 ? 'orderByJoin' : 'orderBy';

            if (count($ex) > 1)
                $this->model->getModel()->setAttribute($ex[0], null);

            $this->model = $this->model->{$sort_method}($sort, in_array($orderColumns[$key] ?? null, ['desc', 'asc']) ? $orderColumns[$key] : 'asc');
        }
    }

    protected function resolveColumnSearchString(mixed $model, string $dotted_column_name, mixed $searchField, string $operator = 'and'): mixed {
        $column = explode('.', $dotted_column_name);
        $has = count($column) > 1;
        $tableName = trim($has ? self::getTableName($model, $column) : $this->table_name);
        $columnName = $has ? implode('.', $column) : $tableName . '.' . $column[0];
        $columnLast = $column[count($column) - 1];

        if (isset($this->columnTypes[$tableName][$columnLast])) {
            $type = $this->columnTypes[$tableName][$columnLast];
        } else {
            if (!$model->getConnection()->getSchemaBuilder()->hasColumn($tableName, $columnLast))
                return $model; # Return model if column is not in table

            $type = $this->columnTypes[$tableName][$columnLast] = $model->getConnection()->getSchemaBuilder()->getColumnType($tableName, $columnLast);
        }

        $search = empty($searchField['operator'] ?? null) ? $searchField : ($searchField['value'] ?? null);

        if ($search && in_array($type, self::NUMERIC_TYPES) && !is_numeric($search) && !is_array($search) && ($searchField['operator'] ?? null) !== 'wc')
            return $model; # Return model if trying to search different data type

        $searchOper = $searchField['operator'] ?? config('datatable.default_search_operator');
        $primaryWhere = $operator === 'or' ? 'orWhere' : 'where';
        $where = $has ? 'whereJoin' : 'where';
        $nullWhere = $has ? 'orWhereJoin' : 'orWhere';
        $searchOperator = self::resolveSearchOperator($searchOper, $type, $this->driver);

        if ($searchOper === 'wc' && isset($searchField['value']))
            return $model->$primaryWhere(function ($query) use ($columnName, $searchField, $operator, $tableName) {
                $query->where(function ($dummyQuery) use ($columnName, $searchField) {
                    if (str_contains($columnName, '.'))
                        $dummyQuery
                            ->orWhereJoin($columnName, '!=', null)
                            ->orWhereJoin($columnName, '=', null);

                    if (str_contains($searchField['value'], '.'))
                        $dummyQuery
                            ->orWhereJoin($searchField['value'], '!=', null)
                            ->orWhereJoin($searchField['value'], '=', null);
                });

                $columnName = count($parts = explode('.', $columnName)) === 1 ? $columnName : implode('.', array_slice($parts, -2));
                $searchField['value'] = count($parts = explode('.', $searchField['value'])) === 1 ? $searchField['value'] : implode('.', array_slice($parts, -2));

                if (!str_contains($columnName, '.'))
                    $columnName = $this->table_name . '.' . $columnName;

                if (!str_contains($searchField['value'], '.'))
                    $searchField['value'] = $this->table_name . '.' . $searchField['value'];

                if ($operator === 'and')
                    $query->whereColumn($columnName, '=', $searchField['value']);

                if ($operator === 'or')
                    $query->orWhereColumn($columnName, '=', $searchField['value']);
            });

        if ($searchOper === 'nn') 
            return $model->$primaryWhere(function ($query) use ($columnName, $where) {
                $query->$where($columnName, '!=', null);
            });

        if ($searchOper === 'nu')
            return $model->$primaryWhere(function ($query) use ($columnName, $where) {
                $query->$where($columnName, '=', null);
            });

        if ($searchOper === 'bw' && is_array($search) && count($search) === 2)
            return $model->$primaryWhere(function ($query) use ($columnName, $where, $search) {
                $query->$where($columnName, '>=', $search[0])->$where($columnName, '<=', $search[1]);
            });

        if (is_array($search)) {
            $whereOperator = self::resolveSearchConnectors(in_array($searchOper, ['nl', 'ne']) ? 'and' : 'or', $where, $searchOper);

            return $model->$primaryWhere(function ($model) use ($search, $whereOperator, $columnName, $searchOper, $type, $nullWhere, $searchOperator, $column) {
                foreach ($search as $searchString)
                    $model = $this->resolveColumnSpecificSearchString($model, $searchString, $whereOperator, $columnName, $searchOper, $type, $searchOperator, $column);

                if ($searchOper === 'ne')
                    $model->$nullWhere($columnName, '=', null);
            });
        }

        $whereOperator = self::resolveSearchConnectors($operator, $where, $searchOper);

        return $model->$primaryWhere(function ($model) use ($search, $whereOperator, $columnName, $searchOper, $type, $nullWhere, $searchOperator, $column) {
            $model = self::resolveColumnSpecificSearchString($model, $search, $whereOperator, $columnName, $searchOper, $type, $searchOperator, $column);

            if (!$search || $searchOper === 'ne')
                $model->$nullWhere($columnName, '=', null);
        });
    }

    protected static function resolveSearchOperator(string $operation, string $type, mixed $driver): string {
        $compare = 'LIKE';

        if (in_array($type, self::NUMERIC_TYPES))
            $compare = '=';

        if ($type === 'boolean')
            $compare = 'IS';

        return [
            'eq' => ['=' => '=', 'IS' => '=', 'LIKE' => '='],
            'lk' => ['=' => '=', 'IS' => '=', 'LIKE' => ($driver === self::PGSQL_DRIVER ? 'ILIKE' : 'LIKE')],
            'ne' => ['=' => '!=', 'IS' => '!=', 'LIKE' => '!=', ],
            'gt' => ['=' => '>', 'IS' => '=', 'LIKE' => '>'],
            'lt' => ['=' => '<', 'IS' => '=', 'LIKE' => '<'],
            'ge' => ['=' => '>=', 'IS' => '=', 'LIKE' => '>='],
            'le' => ['=' => '<=', 'IS' => '=', 'LIKE' => '<='],
            'nl' => ['=' => '=', 'IS' => '!=', 'LIKE' => 'NOT ' . ($driver === self::PGSQL_DRIVER ? 'ILIKE' : 'LIKE')]
        ][$operation][$compare] ?? '=';
    }

    protected static function resolveSearchString(string $operation, string $type, mixed $search): mixed {
        if ($type === 'boolean')
            return self::toBooleanValue($search);

        if (in_array($type, self::NUMERIC_TYPES))
            return $search;

        if (in_array($operation, ['lt', 'gt', 'le', 'ge', 'eq', 'ne']))
            return $search;

        if (isset($search))
            return '%' . $search . '%';

        return '';
    }

    protected static function resolveSearchConnectors(string $relation, string $laravelOperation, string $searchOperation = 'eq'): string {
        return [
            'and' => [
                'eq' => ['where' => 'where', 'whereJoin' => 'whereJoin', 'whereInJoin' => 'whereInJoin', 'whereIn' => 'whereIn'],
                'ne' => ['where' => 'where', 'whereJoin' => 'whereJoin', 'whereInJoin' => 'whereNotInJoin', 'whereIn' => 'whereNotIn']
            ],

            'or' => [
                'eq' => ['where' => 'orWhere', 'whereJoin' => 'orWhereJoin', 'whereInJoin' => 'orWhereInJoin', 'whereIn' => 'orWhereIn'],
                'ne' => ['where' => 'orWhere', 'whereJoin' => 'orWhereJoin', 'whereInJoin' => 'orWhereNotInJoin', 'whereIn' => 'orWhereNotIn']
            ]
        ][$relation][$searchOperation === 'ne' ? 'ne' : 'eq'][$laravelOperation] ?? 'where';
    }

    protected static function resolveColumnSpecificSearchString(mixed $model, mixed $search, string $whereoperator, string $columnName, string $searchOper, string $type, string $searchOperator, array $column): mixed {
        $isTranslatableEnabled = config('datatable.allow_translatable');
        $isJsonType = $type === 'json';
        $isNotJoin = !str_contains($whereoperator, 'join');
        $isModelTranslatable = property_exists($model->getModel(), 'translatable');
        $isColumnTranslatable = $isModelTranslatable && is_array($model->getModel()->translatable) && in_array(end($column), $model->getModel()->translatable);

        if ($isTranslatableEnabled && $isJsonType && $isNotJoin && $isColumnTranslatable)
            $model->$whereoperator($columnName . '->' . app()->getLocale(), $searchOperator, self::resolveSearchString($searchOper, $type, $search));
        else
            $model->$whereoperator($columnName, $searchOperator, self::resolveSearchString($searchOper, $type, $search));

        return $model;
    }

    protected static function getTableName(mixed $model, array $columns): string {
        $relation = $model->getModel();

        foreach (array_slice($columns, 0, -1) as $column)
            $relation = $relation->{$column}()->getRelated();

        return $relation->getModel()->getTable();
    }

    protected static function toBooleanValue(mixed $value): int {
        if ($value === 'false' || $value === '0')
            return 0;

        if ($value === 'true' || $value === '1')
            return 1;

        return intval($value);
    }
}
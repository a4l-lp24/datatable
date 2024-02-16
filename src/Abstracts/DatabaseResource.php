<?php

namespace DataTable\Abstracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model AS EloquentModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use DataTable\Interfaces\IDatabaseResource;
use Illuminate\Support\Facades\DB;

abstract class DatabaseResource implements IDatabaseResource
{

    protected EloquentModel|Builder|Relation $model;
    protected Request $request;
    protected array $with = [];
    protected array $schema = [];
    protected array $selectColumns = [];
    protected array $selectWiths = [];
    protected string $table_name;
    protected array $table_columns = [];
    protected array $columns = [];
    protected array $select = ["*"];
    private array $columnTypes = [];
    protected int $per_page = 50;
    protected const PAGE_NAME = "page";
    protected const PER_PAGE_NAME = "per_page";
    protected const SELECT_COLUMN_NAME = "select";
    protected const ORDER_COLUMN_NAME = "orderColumn";
    protected const ORDER_DIRECTION_NAME = "orderDirection";
    protected bool $strict_with_mode = true;
    protected const PREVENTED_COLUMN_NAMES = [self::ORDER_COLUMN_NAME, self::ORDER_DIRECTION_NAME, self::SELECT_COLUMN_NAME, self::PAGE_NAME, self::PER_PAGE_NAME];
    protected const NUMERIC_TYPES = ["integer", "smallint", "bigint", "float", "decimal"];
    private string|null $driver = null;
    private const PGSQL_DRIVER = "pgsql";

    public function init(EloquentModel|Builder|Relation $model, Request $request, $with = [], $schema = []): DatabaseResource
    {

        $this->model = $model;
        $this->request = $request;
        $this->with = $with;
        $this->schema = $schema;
        $this->table_name = $this->model->getModel()->getTable();
        $this->table_columns = $this->model->getConnection()->getSchemaBuilder()->getColumnListing($this->table_name);
        $this->strict_with_mode = config("datatable.strict_with_mode", true);
        $this->per_page = config("datatable.default_per_page", 50);
        $this->driver = DB::connection()->getDriverName();
        $this->build();

        return $this;

    }

    abstract protected function build(): DatabaseResource;

    protected function filterColumns($columns){

        foreach ($columns as $kcol => $column) {

            if(is_array($column)){
                $keys = [($column["data"] ?? $column["key"] ?? $kcol)];
                if (!empty($column["text"])) $keys[] = $column["text"];    
            }else{
                $keys = [$kcol];
            }

            foreach ($keys as $key) {

                $ex = explode(".", $key);

                // select column only
                if (count($ex) === 1) {

                    if (in_array($ex[0], $this->table_columns) and !in_array($this->table_name . "." . $ex[0], $this->selectColumns)) $this->selectColumns[] = $this->table_name . "." . $ex[0];
                    if (!in_array($key, $this->table_columns)) unset($columns[$key]);

                // select relationship column, check within allowed relations
                } else {

                    $selKey = implode(".", array_slice($ex, 0, count($ex) - 1));

                    if (!in_array($selKey, $this->selectWiths) AND $this->checkRelationPermissions($selKey)) {

                        $this->selectWiths[] = $selKey;

                        if (method_exists($this->model->getModel(), $ex[0])) {
                            $this->model->getModel()->setAttribute($ex[0], null);
                            $relation = $this->model->getModel()->{$ex[0]}();
                            $method = method_exists($relation, "getLocalKeyName") ? "getLocalKeyName" : (method_exists($relation, "getParentKeyName") ? "getParentKeyName" : "getForeignKeyName");
                            $selectTableName = $this->table_name . "." . $relation->{$method}();
                            if (!in_array($selectTableName, $this->selectColumns)) $this->selectColumns[] = $selectTableName;
                            if (!in_array($selectTableName, $this->selectColumns) and !in_array($selKey, $this->selectWiths)) unset($columns[$kcol]);
                        }

                    }

                }
            }
        }  

        $this->setColumns($columns);
    }

    private function checkRelationPermissions($key): bool
    {

        if(!$this->strict_with_mode) return true;

        foreach($this->with AS $with){
            if((!is_array($with) AND $with === $key) OR (is_array($with) AND !empty($with["relation"]) AND $with["relation"] === $key AND (empty($with["permissions"]) OR (Auth::user() AND Auth::user()->Auth::user()->hasAnyPermission($with["permissions"]))))) return true;
        }

        return false;

    }

    protected function setWith(array $with){
        $this->with = $with;
        $this->model = $this->model->with($with);
        return $this;
    }

    protected function setColumns(array $columns){
        
        $columns = array_filter($columns, function($key){
            return !in_array($key, self::PREVENTED_COLUMN_NAMES);
        }, ARRAY_FILTER_USE_KEY);
        $this->columns = $columns;
    }

    protected function setSelect(array $select){
        $this->select = $select;
        $this->model = $this->model->select($this->select);
    }

    protected function sortData(){

        $sort_column = $this->request->{self::ORDER_COLUMN_NAME} ?? null;
        $sort_array = is_array($sort_column) ? $sort_column : [$sort_column];
        $order_column = $this->request->{self::ORDER_DIRECTION_NAME} ?? null;
        $order_array = is_array($order_column) ? $order_column : [$order_column];

        if ($sort_column) {

            foreach($sort_array AS $key => $sort){
                $ex = explode(".", $sort);
                $sort_method = (count($ex) > 1) ? "orderByJoin" : "orderBy";
                if(count($ex) > 1) $this->model->getModel()->setAttribute($ex[0], null);
                $this->model = $this->model->{$sort_method}($sort, (!empty($order_array[$key]) AND in_array($order_array[$key], ["desc", "asc"])) ? $order_array[$key] : "asc");
            }
        }

    }

    protected function paginate(){
        
        return $this->model->paginate((!empty($this->request->{self::PER_PAGE_NAME}) and is_numeric($this->request->{self::PER_PAGE_NAME})) ? $this->request->{self::PER_PAGE_NAME} : $this->per_page);
    
    }

    protected static function resolveSearchOperator($oper, $type, $driver)
    {
        $like_operator = $driver === self::PGSQL_DRIVER ? "ILIKE" : "LIKE";

        $phpoper = in_array($type, self::NUMERIC_TYPES) ? "=" : ($type === "boolean" ? "IS" : "LIKE");
        $opers = [
            "eq" => ["=" => "=", "LIKE" => "=", "IS" => "="],
            "lk" => ["=" => "=", "LIKE" => $like_operator, "IS" => "="],
            "ne" => ["=" => "!=", "LIKE" => "!=", "IS" => "!="],
            "gt" => ["=" => ">", "LIKE" => ">", "IS" => "="],
            "lt" => ["=" => "<", "LIKE" => "<", "IS" => "="],
            "ge" => ["=" => ">=", "LIKE" => ">=", "IS" => "="],
            "le" => ["=" => "<=", "LIKE" => "<=", "IS" => "="],
            "nl" => ["=" => "=", "LIKE" => "NOT " . $like_operator, "IS" => "!="]
        ];
        return array_key_exists($oper, $opers) ? $opers[$oper][$phpoper] : "=";
    }

    protected static function getBoolVal($val){
        return $val === "false" ? 0 : ($val === "true" ? 1 : ($val === "0" ? 0 : ($val === "1" ? 1 : intval($val))));
    }

    protected static function resolveSearchString($oper, $type, $search)
    {
        return ($type === "boolean" ? self::getBoolVal($search) : ((in_array($type, self::NUMERIC_TYPES) or in_array($oper, ["lt", "gt", "le", "ge", "eq", "ne"])) ? $search : (isset($search) ? ($type === "json" ? "%" . $search . "%" : "%".$search."%") : "")));
    }

    protected static function resolveSearchConnectors($oper, $phpoper, $searchOper = "eq")
    {

        $searchOper = $searchOper === "ne" ? "ne" : "eq";
        $opers = [
            "and" => [
                "eq" => ["where" => "where", "whereJoin" => "whereJoin", "whereInJoin" => "whereInJoin", "whereIn" => "whereIn"],
                "ne" => ["where" => "where", "whereJoin" => "whereJoin", "whereInJoin" => "whereNotInJoin", "whereIn" => "whereNotIn"]
            ],
            "or" => [
                "eq" => ["where" => "orWhere", "whereJoin" => "orWhereJoin", "whereInJoin" => "orWhereInJoin", "whereIn" => "orWhereIn"],
                "ne" => ["where" => "orWhere", "whereJoin" => "orWhereJoin", "whereInJoin" => "orWhereNotInJoin", "whereIn" => "orWhereNotIn"]
            ]
        ];
        return array_key_exists($oper, $opers) ? $opers[$oper][$searchOper][$phpoper] : "where";
    }

    protected static function getTableName($model, array $column)
    {

        $relation = $model->getModel();
        for ($i = 0; $i < (count($column) - 1); $i++) {
            $relation = $relation->{$column[$i]}()->getRelated();
        }
        return $relation->getModel()->getTable();
        
    }

    protected function resolveColumnSearchString($model, $dotted_column_name, $searchField, $operator = "and")
    {

        $column = explode(".", $dotted_column_name);
        $has = count($column) > 1 ? true : false;
        $tableName = count($column) > 1 ? self::getTableName($model, $column) : $this->table_name;


        if(isset($this->columnTypes[preg_replace('/$/', '', $tableName)][$column[count($column) - 1]])){
            $type = $this->columnTypes[preg_replace('/$/', '', $tableName)][$column[count($column) - 1]];
        }else{
            // return model if this column is not in table
            if(!$model->getConnection()->getSchemaBuilder()->hasColumn(preg_replace('/$/', '', $tableName), $column[count($column) - 1])) return $model;
            
            // get data type of column (integer, float etc)
            $type = $model->getConnection()->getSchemaBuilder()->getColumnType(preg_replace('/$/', '', $tableName), $column[count($column) - 1]);
            $this->columnTypes[preg_replace('/$/', '', $tableName)][$column[count($column) - 1]] = $type;
        }

        // get search string
        $search = !empty($searchField["operator"]) ? ($searchField["value"] ?? null) : $searchField;

        // return model if trying to search different data type
        if ($search and in_array($type, self::NUMERIC_TYPES) and (!is_numeric($search) and !is_array($search))) return $model;

        // define operators etc...
        $searchOper = $searchField["operator"] ?? config("datatable.default_search_operator");
        $primaryWhere = $operator ==="or" ? "orWhere" : "where";
        $where = $has ? "whereJoin" : "where";
        $nullWhere = $has ? "orWhereJoin" :  "orWhere";
        $columnName = $has ? implode(".", $column) : $tableName . "." . $column[0];
        $searchOperator = self::resolveSearchOperator($searchOper, $type, $this->driver);

        // column cant be null
        if ($searchOper === "nn") {

            $model->$where($columnName, "!=", null);

        // column is null
        } else if ($searchOper === "nu") {

            $model->$where($columnName, "=", null);

        // value between
        } else if ($searchOper === "bw" AND is_array($search) AND count($search) === 2) {

            $model->$where($columnName, ">=", $search[0]);
            $model->$where($columnName, "<=", $search[1]);

        } else {

            // search in array of expressions
            if (is_array($search)) {

                $whereoperator = self::resolveSearchConnectors(in_array($searchOper, ["nl", "ne"]) ? "and" : "or", $where, $searchOper);
                $model = $model->$primaryWhere(function($model) use ($search, $whereoperator, $columnName, $searchOper, $type, $nullWhere, $searchOperator, $column) {
                    foreach ($search as $searchString) {

                        $model = $this->resolveColumnSpecificSearchString($model, $searchString, $whereoperator, $columnName, $searchOper, $type, $searchOperator, $column);

                    }
                    if ($searchOper === "ne") $model->$nullWhere($columnName, "=", null);
                });

            // search in single expression
            } else {

                $whereoperator = self::resolveSearchConnectors($operator, $where, $searchOper);

                $model = $model->$primaryWhere(function($model) use ($search, $whereoperator, $columnName, $searchOper, $type, $nullWhere, $searchOperator, $column) {

                    $model = self::resolveColumnSpecificSearchString($model, $search, $whereoperator, $columnName, $searchOper, $type, $searchOperator, $column);

                    if (!$search or $searchOper === "ne") $model->$nullWhere($columnName, "=", null);

                });
            }

        }

        return $model;
    }

    private static function resolveColumnSpecificSearchString($model, $search, $whereoperator, $columnName, $searchOper, $type, $searchOperator, $column){

        // If spatie translatable feature is enabled https://spatie.be/docs/laravel-translatable/ 
        if(config("datatable.allow_translatable") AND $type === "json" AND strpos($whereoperator, "join") === false AND property_exists($model->getModel(), "translatable") AND is_array($model->getModel()->translatable) AND in_array(end($column), $model->getModel()->translatable)){
            $model->$whereoperator($columnName . "->" . app()->getLocale(), $searchOperator, self::resolveSearchString($searchOper, $type, $search));
        }else{
            $model->$whereoperator($columnName, $searchOperator, self::resolveSearchString($searchOper, $type, $search));
        }

        return $model;

    }

    public function getData(): LengthAwarePaginator
    {

        return $this->paginate();

    }

    public function getBuilder(): EloquentModel|Builder
    {

        return $this->model;

    }

}

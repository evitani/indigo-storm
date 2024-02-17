<?php

namespace Core\Db2\Models;

class SearchQuery{

    private $baseQuery = "SELECT name FROM |TABLENAME||CONDITION||ORDER||LIMIT|";
    private $condition;
    private $limit;
    private $orderQ = " ORDER BY id DESC";

    private $finalQuery = null;

    private $baseSubQuery = "`id` IN (SELECT |OBJECTNAME|Id FROM |TABLENAME|__|SUBTABLENAME| WHERE `dataKey` = \"|DATAKEY|\" AND |COMPARISON|)";
    private $baseSubQueryMainTable = "`id` IN (SELECT `id` FROM |TABLENAME| WHERE |COMPARISON|)";
    private $orderQuery = "(SELECT `dataValue` FROM |TABLENAME|__|SUBTABLENAME| WHERE |OBJECTNAME|Id = |TABLENAME|.id AND `dataKey` = \"|DATAKEY|\")";

    private $objectName;
    private $objectTable;

    private $operators = array(
        'eq'    => '`|FIELD|` = |CRITERIA|',
        'neq'   => '`|FIELD|` != |CRITERIA|',
        'in'    => '`|FIELD|` IN (|CRITERIA|)',
        'nin'   => '`|FIELD|` NOT IN (|CRITERIA|)',
        'like'  => '`|FIELD|` LIKE |CRITERIA|',
        'nlike' => '`|FIELD|` NOT LIKE |CRITERIA|',
        'lt'    => 'CAST(`|FIELD|` AS DECIMAL) < |CRITERIA|',
        'gt'    => 'CAST(`|FIELD|` AS DECIMAL) > |CRITERIA|',
        'lte'   => 'CAST(`|FIELD|` AS DECIMAL) <= |CRITERIA|',
        'gte'   => 'CAST(`|FIELD|` AS DECIMAL) >= |CRITERIA|',
    );

    private $operatorMap = array(
        '=' => 'eq',
        '==' => 'eq',
        '!=' => 'neq',
        '!==' => 'neq',
        '~' => 'like',
        '!~' => 'nlike',
        '<' => 'lt',
        '>' => 'gt',
        '<=' => 'lte',
        '>=' => 'gte',
        '=<' => 'lte',
        '=>' => 'gte',
    );

    private $storedItems = array();

    private function setObjectDetails($object){
        $objectClass = explode("\\", get_class($object));
        $this->objectName = $objectClass[count($objectClass) - 1];
        $this->objectTable = $this->objectName . "s";
    }

    public function __construct($object){
        $this->setObjectDetails($object);
        unset($object);
    }

    private function wrapTerms($term){
        if(is_array($term)){
            $return = array();
            foreach($term as $termItem){
                $return[] = $this->wrapTerms($term);
            }
        }elseif(is_string($term)){
            return "\"" . $term . "\"";
        }else{
            return $term;
        }
    }

    private function constructCondition($field, $operator, $comparator){
        global $Application;
        $fieldMap = explode(".", $field);
        $operator = strtolower($operator);

        $comparator = $Application->db2->escape($comparator);
        $comparator = $this->wrapTerms($comparator);

        if(array_key_exists($operator, $this->operatorMap)){
            $operator = $this->operatorMap[$operator];
        }

        if(!array_key_exists($operator, $this->operators)){
            throw new \Exception('Invalid operator ' . $operator, 500);
        }

        $fieldMap = $Application->db2->escape($fieldMap);

        if(strtolower($fieldMap[0]) === 'name' || strtolower($fieldMap[0]) === 'id'){
            $field = 'name';
            $mainTable = true;
        }elseif(count($fieldMap) !== 2){
            throw new \Exception('DataTable could not be inferred, requires exactly 2 parameters', 500);
        }else{
            $subTable = $fieldMap[0];
            $field = "dataValue";
            $dataKey = $fieldMap[1];
            $mainTable = false;
        }

        if(($operator == 'in' || $operator == 'nin') && is_array($comparator)){
            $comparator = implode(", ", $comparator);
        }

        $condition = str_replace(
            array('|FIELD|','|CRITERIA|'),
            array($field, $comparator),
            $this->operators[$operator]
        );

        if($mainTable){
            $condition = str_replace(
                array('|TABLENAME|', '|COMPARISON|'),
                array($this->objectTable, $condition),
                $this->baseSubQueryMainTable
            );
        }else{
            $condition = str_replace(
                array('|TABLENAME|', '|COMPARISON|', '|SUBTABLENAME|', '|DATAKEY|', '|OBJECTNAME|'),
                array($this->objectTable, $condition, $subTable, $dataKey, $this->objectName),
                $this->baseSubQuery
            );
        }

        return $condition;

    }

    private function getQueries($functionArguments){
        $queries = array();

        foreach($functionArguments as $arg){
            if(is_array($arg) && count($arg) === 3){
                $queries[] = $this->constructCondition($arg[0], $arg[1], $arg[2]);
            }elseif(is_int($arg) && $arg < count($this->storedItems)){
                $queries[] = $this->storedItems[$arg];
            }elseif(is_array($arg)){
                throw new \Exception("Malformed criteria, expects 3 parameters", 500);
            }else{
                throw new \Exception("Malformed criteria, requires condition or nested query", 500);
            }
        }

        return $queries;
    }

    public function getQuerySql(){
        return $this->finalQuery;
    }

    public function _and(){
        $queries = $this->getQueries(func_get_args());
        $query  = "(" . implode(" AND ", $queries) . ")";
        $this->storedItems[] = $query;
        return count($this->storedItems) - 1;
    }

    public function _or(){
        $queries = $this->getQueries(func_get_args());
        $query  = "(" . implode(" OR ", $queries) . ")";
        $this->storedItems[] = $query;
        return count($this->storedItems) - 1;
    }

    public function filter($query){
        $this->condition = " WHERE ";
        if(is_int($query) && $query < count($this->storedItems)){
            $this->condition .= $this->storedItems[$query];
        }elseif(is_array($query) && count($query) === 3){
            $this->condition .= $this->constructCondition($query[0], $query[1], $query[2]);
        }else{
            throw new \Exception('Invalid query reference or condition', 500);
        }

        return $this;
    }

    public function limit($limit, $offset = null){
        if(is_int($limit) && is_int($offset)){
            $limitString = " LIMIT $offset, $limit";
        }elseif(is_int($limit)){
            $limitString = " LIMIT $limit";
        }else{
            throw new \Exception('Malformed limit', 500);
        }
        $this->limit = $limitString;
        return $this;
    }

    public function order($field, $order){
        global $Application;

        if(in_array($order, array(ORDER_ASC, ORDER_DESC))){
            $field = $Application->db2->escape($field);
            if($field == 'name' || $field == 'id'){
                $partial = "`" . $field . "` " . $order;
            }else{
                $field = explode('.', $field);
                $partial = str_replace(
                    array('|TABLENAME|','|SUBTABLENAME|', '|OBJECTNAME|', '|DATAKEY|'),
                    array($this->objectTable, $field[0], $this->objectName, $field[1]),
                    $this->orderQuery
                ) . " " . $order;
            }

            $this->orderQ = " ORDER BY " . $partial;
            return $this;

        }else{

            throw new \Exception('Malformed order', 500);

        }
    }

    public function run(){
        global $Application;

        $query = str_replace(
            array('|TABLENAME|','|CONDITION|','|LIMIT|','|ORDER|'),
            array($this->objectTable, $this->condition, $this->limit, $this->orderQ),
            $this->baseQuery);

        $this->finalQuery = $query;

        return $Application->db2->fulfillSearchQuery($this);
    }

}

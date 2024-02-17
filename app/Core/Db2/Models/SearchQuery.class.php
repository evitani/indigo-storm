<?php

namespace Core\Db2\Models;

class SearchQuery{

    private $baseQuery = "SELECT name FROM |TABLENAME||CONDITION||ORDER||LIMIT|";
    private $condition = ' WHERE status = "ACTIVE"';
    private $limit;
    private $orderQ = " ORDER BY id DESC";

    private $finalQuery = null;

    private $baseSubQuery = "`id` IN (SELECT |OBJECTNAME|Id FROM |TABLENAME|__|SUBTABLENAME| WHERE `dataKey` = \"|DATAKEY|\" AND |COMPARISON|)";
    private $baseSubQueryMainTable = "`id` IN (SELECT `id` FROM |TABLENAME| WHERE |COMPARISON|)";
    private $orderQuery = "(SELECT `dataValue` FROM |TABLENAME|__|SUBTABLENAME| WHERE |OBJECTNAME|Id = |TABLENAME|.id AND `dataKey` = \"|DATAKEY|\")";

    private bool $forInterface;
    private array $ifStoredItems = [];
    private array $ifCondition;
    private array $ifLimit;

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

    public function __construct($object = null){
        if (!is_null($object)){
            $this->forInterface = false;
            $this->setObjectDetails($object);
            unset($object);
        } else {
            $this->forInterface = true;
        }
    }

    private function wrapTerms($term){
        if(is_array($term)){
            $return = array();
            foreach($term as $termItem){
                $return[] = $this->wrapTerms($termItem);
            }
            return $return;
        }elseif(is_string($term)){
            return "\"" . $term . "\"";
        }else{
            return $term;
        }
    }

    private function constructCondition($field, $operator, $comparator){
        global $indigoStorm;

        $fieldMap = explode(".", $field);
        $operator = strtolower($operator);

        $comparator = $indigoStorm->getDb2()->escape($comparator);
        $comparator = $this->wrapTerms($comparator);

        if(array_key_exists($operator, $this->operatorMap)){
            $operator = $this->operatorMap[$operator];
        }

        if(!array_key_exists($operator, $this->operators)){
            throw new \Exception('Invalid operator ' . $operator, 500);
        }

        $fieldMap = $indigoStorm->getDb2()->escape($fieldMap);

        if(strtolower($fieldMap[0]) === 'name' || strtolower($fieldMap[0]) === 'id'){
            $field = strtolower($fieldMap[0]);
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

    public function unpack(array $package) {
        if ($this->forInterface) {
            throw new \Exception('Can only unpack into genuine searches', 500);
        }

        // Condition
        if (array_key_exists('condition', $package)) {
            $condition = $package['condition'];
            if (count($condition) === 3) {
                $this->filter($condition);
            } elseif (
                count($condition) === 2 &&
                array_key_exists('t', $condition) &&
                array_key_exists('q', $condition)
            ) {
                $this->filter($this->_unpackQuery($condition));
            }
        }

        // Limit
        if (array_key_exists('limit', $package)) {
            if (count($package['limit']) == 2) {
                $this->limit($package['limit'][0],$package['limit'][1]);
            } else {
                $this->limit($package['limit'][0]);
            }
        }
    }

    private function _unpackQuery(array $condition) {
        $unpacked = [];

        foreach ($condition['q'] as $q) {
            if (count($q) === 3) {
                $unpacked[] = $q;
            } elseif (count($q) === 2) {
                $unpacked[] = $this->_unpackQuery($q);
            } else {
                throw new \Exception('Cannot unpack condition', 500);
            }
        }

        $unpacked = call_user_func_array([$this, '_' . strtolower($condition['t'])], $unpacked);

        return $unpacked;
    }

    public function pack(): array {
        if (!$this->forInterface) {
            throw new \Exception('Can only pack interface searches', 500);
        }
        $pack = [];
        if (isset($this->ifCondition)) {
            $pack['condition'] = $this->ifCondition;
        }
        if (isset($this->ifLimit)) {
            $pack['limit'] = $this->ifLimit;
        }
        return $pack;
    }

    public function _and(){
        if ($this->forInterface) {
            $queries = func_get_args();
            $this->ifStoredItems[] = ['t' => 'AND', 'q' => $queries];
            return count($this->ifStoredItems) - 1;
        } else {
            $q = func_get_args();
            if (count($q) === 1 && is_array($q[0])) {
                $q = $q[0];
            }
            $queries = $this->getQueries($q);
            $query  = "(" . implode(" AND ", $queries) . ")";
            $this->storedItems[] = $query;
            return count($this->storedItems) - 1;
        }
    }

    public function _or(){
        if ($this->forInterface) {
            $queries = func_get_args();
            $this->ifStoredItems[] = ['t' => 'OR', 'q' => $queries];
            return count($this->ifStoredItems) - 1;
        } else{
            $q = func_get_args();
            if (count($q) === 1 && is_array($q[0]) && count($q[0]) === 3) {
                $q = $q[0];
            }
            $queries = $this->getQueries($q);
            $query = "(" . implode(" OR ", $queries) . ")";
            $this->storedItems[] = $query;

            return count($this->storedItems) - 1;
        }
    }

    private function _collapseIfQuery($startPoint) {
        $collapsed = $this->ifStoredItems[$startPoint];
        foreach ($collapsed['q'] as $id => $val) {
            if (is_numeric($val)) {
                $collapsed['q'][$id] = $this->_collapseIfQuery($val);
            }
        }
        return $collapsed;
    }

    public function filter($query){
        if ($this->forInterface) {
            if (is_int($query) && $query < count($this->ifStoredItems)) {
                $collapsed = $this->_collapseIfQuery($query);
            } elseif (is_array($query) && count($query) === 3) {
                $collapsed = $query;
            } else {
                throw new \Exception('Invalid query reference or condition', 500);
            }
            $this->ifCondition = $collapsed;
            return $this;
        } else {
            if ($this->condition === ' WHERE status = "ACTIVE"') {
                $this->condition .=  ' AND ';
            }
            if(is_int($query) && $query < count($this->storedItems)){
                $this->condition .= $this->storedItems[$query];
            }elseif(is_array($query) && count($query) === 3){
                $this->condition .= $this->constructCondition($query[0], $query[1], $query[2]);
            }else{
                throw new \Exception('Invalid query reference or condition', 500);
            }
        }

        return $this;
    }

    public function limit($limit, $offset = null){
        if ($this->forInterface) {
            if (!is_numeric($limit) || (!is_numeric($offset) && !is_null($offset))) {
                throw new \Exception('Malformed limit', 500);
            } else {
                $this->ifLimit = [$limit];
                if (!is_null($offset)) {
                    $this->ifLimit[] = $offset;
                }
            }
        } else {
            if(is_numeric($limit) && is_numeric($offset)){
                $limitString = " LIMIT $offset, $limit";
            }elseif(is_numeric($limit)){
                $limitString = " LIMIT $limit";
            }else{
                throw new \Exception('Malformed limit', 500);
            }
            $this->limit = $limitString;
        }
        return $this;
    }

    public function order($field, $order){
        global $indigoStorm;

        if(in_array($order, array(ORDER_ASC, ORDER_DESC))){
            $field = $indigoStorm->getDb2()->escape($field);
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
        global $indigoStorm;

        if ($this->forInterface) {
            throw new \Exception('Cannot run a search designed for interfaces', 500);
        }

        $query = str_replace(
            array('|TABLENAME|','|CONDITION|','|LIMIT|','|ORDER|'),
            array($this->objectTable, $this->condition, $this->limit, $this->orderQ),
            $this->baseQuery);

        $this->finalQuery = $query;

        return $indigoStorm->getDb2()->fulfillSearchQuery($this);
    }

}

<?php

namespace Core\Db2\Models;

use mysqli;

/**
 * Class Database2
 * @package Core\Db2\Models
 */
class Database2{

    /**
     * @var mysqli The mysqli database connection.
     */
    protected $dbcon;

    /**
     * @var array A register of all object types currently set up in the database
     */
    protected $registry = array();

    /**
     * Db constructor.
     * @param array $config
     * An array of the config information to generate the Db connection. Requires "server", "user", "password", and "db" as keys.
     * @throws \Exception Excepts should the database connection fail
     */
    function __construct($config){
        if(is_array($config) && isset($config['server']) && isset($config['user']) && isset($config['password']) && isset($config['db'])){

            if(isset($config['socket'])){
                $this->dbcon = new mysqli($config['server'], $config['user'], $config['password'], $config['db'], null, $config['socket']);
            }else{
                $this->dbcon = new mysqli($config['server'], $config['user'], $config['password'], $config['db']);
            }

            $this->populateRegistry();
        }else{
            //BAD
            throw new \Exception('Database connection failed', 502);
        }

    }

    public function close() {
        $this->dbcon->close();
    }

    /**
     * Escapes the input ready for including in a query.
     * If the value is an array, this will be iterated
     * and the components escaped (recursively if needed).
     * @param mixed $str
     * Item to escape
     * @param bool $mustBeSingle Enforce the fact that the output must be a single string, not an array of objects
     * @return array|string
     * The input, but escaped for SQL statements
     */
    public function escape($str, $mustBeSingle = false){

        if(!is_array($str)){
            return $this->dbcon->escape_string($str);
        }elseif($mustBeSingle){
            $temp = json_encode($str);

            return $this->dbcon->escape_string($temp);
        }else{
            //array
            $temp = array();
            foreach($str as $ak => $av){
                $temp[$ak] = $this->escape($av);
            }

            return $temp;
        }
    }

    /**
     * Runs an SQL query against the database object and
     * returns a relevant result.
     * @param string $query
     * Valid, escaped SQL query
     * @return array|mixed
     * INSERT & UPDATE statements will return true/false,
     * and SELECT statements will return an array of results.
     */
    public function run($query){

        $errorCheck = $this->dbcon->error || null;

        $result = $this->dbcon->query($query);

        $newError = $this->dbcon->error;

        if($errorCheck !== $newError && !is_null($newError) && $newError !== ''){
            islog(LOG_WARNING, $newError);

            return false;
        }

        if(substr($query, 0, 6) == 'INSERT' || substr($query, 0, 6) == 'UPDATE' || substr($query, 0, 7) == 'REPLACE'){
            return $this->dbcon->insert_id;
        }elseif(substr($query, 0, 12) === 'CREATE TABLE'){
            return $result;
        }elseif(substr($query, 0, 10) === 'SELECT id '){
            $response = $result->fetch_all(MYSQLI_ASSOC);
            $composed = array();
            foreach($response as $foundId){
                $composed[] = intval($foundId['id']);
            }

            return $composed;
        }else{
            if(!is_bool($result)){
                return $result->fetch_all(MYSQLI_ASSOC);
            }else{
                return array();
            }
        }
    }

    /**
     * Create a new base object in the database
     * @param object $object An object to generate in the database
     * @return int The ID of the saved object
     * @throws \Exception Exeption will be thrown if the new object's name is non-unique
     */
    public function generateObject($object){
        if($object->getId() === 0 || is_null($object->getId())){
            $objectName = $this->escape($object->getName());
            $tableName = $this->escape($object->typeOf(true) . "s");
            $query = "INSERT INTO $tableName (name) VALUES ('$objectName')";
            $newId = $this->run($query);
            if($newId !== false && $newId > 0){
                return $newId;
            }else{
                throw new \Exception("Cannot create non-unique resource", 500);
            }
        }else{
            return $object->getId();
        }
    }

    /**
     * Update the name of an object if changed.
     * @param object $object An object that requires an update
     * @return bool Whether the object has been saved successfully
     */
    public function updateObject($object){
        if($object->getId() !== 0){
            $objectId = $this->escape($object->getId());
            $objectName = $this->escape($object->getName());
            $tableName = $this->escape($object->typeOf(true) . "s");
            $query = "UPDATE $tableName SET name = '$objectName' WHERE id = $objectId";

            return $this->run($query);
        }
    }

    /**
     * Save a DataTable's content for a specific object
     * @param object $object The object the DataTable relates to
     * @param DataTable $table The DataTable to save
     * @return bool Whether the save was successful
     */
    public function saveDataTable($object, DataTable $table){

        if(is_numeric($object->getId()) && $object->getId() > 0 && count($table->fetchDataTable()) > 0){
            $objectId = $this->escape($object->getId());
            $objectName = $this->escape($object->typeOf(true));
            $keyName = $objectName . "Id";
            $tableName = $objectName . "s__" . $this->escape($table->getName());

            $existingKeys = $table->getExistingKeys();
            $newKeys = $table->fetchDataTable();
            $deleteList = array();

            foreach($existingKeys as $existingKey){
                if(!array_key_exists($existingKey, $newKeys)){
                    array_push($deleteList, $existingKey);
                }
            }

            if(count($deleteList) > 0){
                $deleteQuery = "DELETE FROM $tableName WHERE $keyName = $objectId AND dataKey IN ";
                $deleteQuery .= "(\"" . implode("\",\"", $this->escape($deleteList)) . "\")";
                $this->run($deleteQuery);
            }

            $query = "REPLACE INTO $tableName ($keyName, dataKey, dataValue) VALUES ";
            $queryLines = array();

            foreach($table->fetchDataTable() as $item => $value){
                $escapeItem = $this->escape($item);
                $escapeValue = $this->escape($value, true);
                $queryLines[] = "($objectId, '$escapeItem', '$escapeValue')";
            }

            $queryLinesImplode = implode(",", $queryLines);

            $query .= $queryLinesImplode;

            return $this->run($query);
        }
    }

    /**
     * Check if an object type exists in the registry, and create it if it does not
     * @param string $objectName The object type name
     * @param array $schema The schema for the object type
     * @return bool Whether the save was successful
     */
    public function registerObject($objectName, $schema){

        if(!$this->checkRegistry($objectName)){
            //Object does not exist in registry, so will need to create it

            $queries = array();

            $queries[] = "CREATE TABLE `{$objectName}s` (
                          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                          `name` varchar(128) DEFAULT NULL,
                          `status` varchar(32) DEFAULT 'ACTIVE',
                          `version` varchar(40) DEFAULT NULL,
                          PRIMARY KEY (`id`),
                          UNIQUE KEY `name` (`name`)
                        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;";

            foreach($schema as $stName => $subTable){
                if(gettype($subTable) === 'object' && get_class($subTable) == DATATABLE_CLASS){
                    $tableName = $stName;
                    $keyType = $subTable->schema['key'];
                    $valType = $subTable->schema['val'];

                    $queries[] = "CREATE TABLE `{$objectName}s__{$tableName}` (
                              `{$objectName}Id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                              `dataKey` $keyType NOT NULL,
                              `dataValue` $valType DEFAULT NULL,
                              PRIMARY KEY (`{$objectName}Id`,`dataKey`),
                              CONSTRAINT `{$objectName}s__{$tableName}>{$objectName}s` FOREIGN KEY (`{$objectName}Id`) REFERENCES `{$objectName}s` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;";
                }
            }

            $success = true;
            foreach($queries as $query){
                if(!$this->run($query)){
                    $success = false;
                }
            }

            if($success){
                $query = "INSERT INTO _Config (objectName, objectVersion) VALUES ('{$objectName}', '2')";
                if($this->run($query) !== false){
                    $this->populateRegistry();

                    return true;
                }else{
                    return false;
                }
            }else{
                return false;
            }

        }else if($this->registry[$objectName] == '1'){

            //Object does exist in registry, but needs updating to db2.2 to support object deletion (and versioning)
            $query = "ALTER TABLE `{$objectName}s`
                        ADD `status` varchar(32) DEFAULT 'ACTIVE',
                        ADD `version` varchar(40) DEFAULT NULL;";
            $this->run($query);
            $query = "REPLACE INTO _Config (objectName, objectVersion) VALUES ('{$objectName}', '2')";
            if($this->run($query) !== false){
                $this->populateRegistry();

                return true;
            }else{
                return false;
            }

        }else{
            return true;
        }

    }

    private function populateRegistry(){
        $list = $this->run("SELECT objectName, objectVersion FROM _Config");

        foreach($list as $item){
            $this->registry[$item['objectName']] = $item['objectVersion'];
        }

    }

    /**
     * Check if an object type exists in the registry
     * @param string $objectName The object type name
     * @return bool Whether the object exists in the registry
     */
    public function checkRegistry($objectName){
        return array_key_exists($objectName, $this->registry);
    }

    /**
     * Find an object by name or ID
     * @param string $objectType the name of the object type
     * @param string|int $term The name or ID of the object
     * @param string $field whether to search by name or ID
     * @return bool|mixed Either return the resulting ID or false if not found
     * @throws \Exception Returns an exception if the search term is not 'name' or 'id'
     */
    public function findObject($objectType, $term, $field){
        if($field === 'id' || $field === 'name'){
            $tableName = $this->escape($objectType) . 's';
            $searchTerm = $this->escape($term);
            if($field === 'name'){
                $searchTerm = "'$searchTerm'";
            }
            $query = "SELECT id FROM $tableName WHERE $field = $searchTerm AND `status` = 'ACTIVE'";
            $result = $this->run($query);
            if(is_array($result) && count($result) == 1){
                return $result[0];
            }else{
                return false;
            }
        }else{
            throw new \Exception("Cannot find by term - use searchObjects for more options", 500);
        }
    }

    /**
     * Gets an single object based on ID
     * @param string $objectType The object type
     * @param int $id the object ID
     * @return bool|mixed Returns either the ID and name of the object or false if not found
     */
    public function getObject($objectType, $id){
        $tableName = $this->escape($objectType) . 's';
        $objectId = $this->escape($id);
        $query = "SELECT id, name FROM $tableName WHERE id = $id AND `status` = 'ACTIVE' LIMIT 1";
        $response = $this->run($query);
        if(count($response) === 1){
            return $response[0];
        }else{
            return false;
        }
    }

    /**
     * Gets the contents of a DataTable relating to an object instance
     * @param string $objectName
     * @param int $objectId
     * @param DataTable $table
     * @return array|mixed
     */
    public function getDataTable($objectName, $objectId, DataTable $table){
        if(is_numeric($objectId) && $objectId > 0){
            $objectId = $this->escape($objectId);
            $objectName = $this->escape($objectName);
            $keyName = $objectName . "Id";
            $tableName = $objectName . "s__" . $this->escape($table->getName());

            $query = "SELECT dataKey, dataValue FROM $tableName WHERE $keyName = $objectId";
            $results = $this->run($query);

            foreach($results as $resultId => $resultValue){
                $payload = $resultValue['dataValue'];
                $originalPayload = $payload;

                $payload = json_decode($payload, true);
                if(is_array($payload) || is_object($payload)){
                    $results[$resultId]['dataValue'] = $payload;
                }
            }

            return $results;
        }
    }

    public function fulfillSearchQuery($searchQuery){

        if(
            !is_object($searchQuery) ||
            get_class($searchQuery) !== SEARCHQUERY_CLASS ||
            $searchQuery->getQuerySql() === null
        ){
            throw new \Exception('Malformed search query', 500);
        }

        return $this->getList($searchQuery->getQuerySql());

    }

    /**
     * Searches for objects in the database based on a combined set of characteristics.
     * @param string $resource
     * The name of the type of resource you are searching for
     * @param string $dataset
     * The value you want to search against. Can be 'name', 'dataKey', or any other key used to store data
     * @param array $criteria
     * An array of search criteria, formatted as `value` => [`searchType` => searchValue/s] (multiple accepted)
     * @param mixed $limit
     * Either null or an array containing 2 integer, a start location (zero index) and length of returned list
     * @param string $glue
     * Either 'AND' or 'OR', used to define how the search should string together multiple criteria
     * @param string $order
     * Either 'DESC' or 'ASC', used to define whether the search should sort object IDs descending or ascending
     * @return array
     * A list of resource names that match the search criteria
     * @throws \Exception
     */
    public function searchForObjects($resource, $dataset, $criteria, $limit = null, $glue = 'AND', $order = 'DESC'){
        islog(LOG_WARNING, "The searchForObjects function is deprecated and will be removed in a future release. Upgrade to SearchQuery now.");
        switch ($dataset){
            case 'name':
            case 'dataKey':
                $searchingName = $dataset;
                break;
            default:
                $searchingName = 'dataValue';
        }

        $allowedGlue = array('AND', 'OR');
        $glue = strtoupper($glue);
        if(!in_array($glue, $allowedGlue)){
            $glue = 'AND';
        }

        $allowedOrders = array('DESC', 'ASC');
        if(!in_array($order, $allowedOrders)){
            $order = "DESC";
        }

        $searchCriteria = array();
        foreach($criteria as $typ => $crit){
            array_push($searchCriteria, $this->generateSearch($typ, $crit, $glue, $searchingName));
        }
        if(in_array($glue, array('AND', 'OR'))){
            if(is_array($limit) && count($limit) == 2){
                $limitF = $this->escape($limit[0]);
                $limitT = $this->escape($limit[1]);
                $limitString = " LIMIT $limitF, $limitT";
            }else{
                $limitString = '';
            }
            $resource = $this->escape($resource);
            $tbl = ucwords($resource) . 's';

            if($searchingName == 'name'){
                $searchCriteria = implode("", $searchCriteria);
                $query = "SELECT name FROM {$tbl} WHERE `status` = 'ACTIVE' AND {$searchCriteria}$limitString";
            }else{
                $subTbl = $tbl . "__" . ucwords($this->escape($dataset));
                $searchCriteria = implode(") AND id IN (SELECT {$resource}Id FROM {$subTbl} WHERE ", $searchCriteria);
                $query = "SELECT name FROM {$tbl} WHERE `status` = 'ACTIVE' AND id IN (SELECT {$resource}Id FROM {$subTbl} WHERE $searchCriteria) ORDER BY id {$order}$limitString";
            }
            $query = str_replace(' AND ()', '', $query);
            $query = str_replace(' OR ()', '', $query);

            return $this->getList($query);
        }else{
            throw new \Exception('Cannot search with this criteria', 500);
        }
    }

    /**
     * @param $searchType
     * @param $criteria
     * @param string $type
     * @param string $searchField Which field to search. Accepted values are 'dataValue' (default), 'dataKey', and 'name'.
     * @return string
     * @throws \Exception
     */
    private function generateSearch($searchType, $criteria, $type = 'AND', $searchField = 'dataValue'){

        if(!in_array($searchField, array('dataValue', 'dataKey', 'name'))){
            throw new \Exception("Can't search with that field");
        }

        $accepted = array(
            'eq'    => '_FIELD_ = _CRITERIA_',
            'neq'   => '_FIELD_ != _CRITERIA_',
            'in'    => '_FIELD_ IN _CRITERIA_',
            'nin'   => '_FIELD_ NOT IN _CRITERIA_',
            'like'  => '_FIELD_ LIKE (_CRITERIA_)',
            'nlike' => '_FIELD_ NOT LIKE (_CRITERIA_)',
            'lt'    => 'CAST(_FIELD_ AS DECIMAL) < _CRITERIA_',
            'gt'    => 'CAST(_FIELD_ AS DECIMAL) > _CRITERIA_',
            'lte'   => 'CAST(_FIELD_ AS DECIMAL) <= _CRITERIA_',
            'gte'   => 'CAST(_FIELD_ AS DECIMAL) >= _CRITERIA_',
        );

        $conditions = array();

        foreach($criteria as $key => $val){
            if(is_array($val)){
                $sanitised = array();
                foreach($val as $splitVal){
                    array_push($sanitised, $this->escape($splitVal));
                }
                $sanitised = "(\"" . implode("\",\"", $sanitised) . "\")";
            }else{
                if(is_numeric($val)){
                    $sanitised = $this->escape($val);
                }else{
                    $sanitised = "\"" . $this->escape($val) . "\"";
                }
            }
            array_push($conditions, str_replace('_FIELD_', $searchField, str_replace('_CRITERIA_', $sanitised, $accepted[$key])));
        }

        if(in_array($type, array('AND', 'OR'))){
            if($searchField == 'name'){
                $fullString = implode(" $type ", $conditions);
            }else{
                $fullString = "dataKey = \"" . $this->escape($searchType) . "\" AND (" . implode(" $type ", $conditions) . ")";
            }

            return $fullString;
        }else{
            throw new \Exception('Cannot search with this criteria', 500);
        }
    }

    private function getList($query){
        if(substr($query, 0, 16) == 'SELECT name FROM'){
            $result = $this->dbcon->query($query);

            if(!is_bool($result)){
                $returned = $result->fetch_all(MYSQLI_NUM);
                $ids = array();
                foreach($returned as $itm){
                    array_push($ids, $itm[0]);
                }

                return $ids;
            }else{
                return array();
            }
        }else{
            throw new \Exception('Cannot get list with statement', 500);
        }
    }

    public function deleteObject($object, $backupAction){
        $tableName = $this->escape($object->typeof(true)) . "s";
        $objectId = $this->escape($object->getId());

        $object->getAll();
        $object->setMetadata('_deletedName', $object->getName());
        $object->persist(SAVE_REVISIONS_NO);
        $queries = array();

        $deletionEvent = $this->escape(uniqid());

        switch($backupAction){
            case DELETE_NOBACKUP:
                $queries[] = "DELETE FROM $tableName WHERE id = $objectId";
                $revisions = $this->searchForObjects('ObjectRevision', 'Metadata', array(
                    'objectId' => array('eq' => $objectId),
                    'objectClass' => array('eq' => get_class($object)),
                ));
                $queries[] = "DELETE FROM ObjectRevisions WHERE name IN (\"" .
                    implode("\",\"", $this->escape($revisions)) . "\")";
                break;
            case DELETE_BACKUP_7D:
                $delBackupAfter = time() + 604800;
            case DELETE_BACKUP_30D:
                if(!isset($delBackupAfter)){
                    $delBackupAfter = time() + 2592000;
                }
                $queries[] = "UPDATE $tableName SET `status` = 'DELETED|{$delBackupAfter}', `name` = '$deletionEvent' WHERE id = $objectId";
                break;
            case DELETE_BACKUP_NEVER:
            default:
                $queries[] = "UPDATE $tableName SET `status` = 'DELETED', `name` = '$deletionEvent' WHERE id = $objectId";
                break;
        }

        foreach($queries as $query){
            $this->run($query);
        }

        return true;

    }

}

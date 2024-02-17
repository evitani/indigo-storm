<?php

namespace Core\Models;

use Core\Db2\Models\DataTable;
use Core\Db2\Models\SearchQuery;

/**
 * Basic class used as the template for other classes.
 * @package Core\Models
 */
class BaseModel{

    /**
     * @var int
     * The ID of the resource, set when creating the resource
     */
    public $id;

    /**
     * @var
     * The name of the resource in its table
     */
    public $name;

    /**
     * @var string
     * Whether to store old versions of the object, log they were changed, or not hold any history at all
     */
    protected $revisionHandling = SAVE_REVISIONS_OBJECT;

    /**
     * @var null|string
     * The previous name for this object instance (if set)
     */
    protected $oldName = null;

    /**
     * @var bool Whether ID lookup should be allowed by the object
     */
    protected $allowEnumeration = false;

    /**
     * @var string How backups should be handled after deletion.
         */
    protected $defaultBackupActivity = DELETE_BACKUP_NEVER;

    protected function addDataTable($tableName, $keyDetails, $valueDetails){
        if($this->getId() === 0 || is_null($this->getId())){
            $newObject = true;
        }else{
            $newObject = false;
        }
        $this->{$tableName} = new DataTable($tableName, $keyDetails, $valueDetails, $newObject);
    }

    /**
     * Save the object to the database
     * @param string $revisionType Whether to save the old copy of the object. Default to saving a full revision.
     * @throws \Exception
     */
    public function persist($revisionType = null){
        global $indigoStorm;

        if(is_null($revisionType)){
            $revisionType = $this->revisionHandling;
        }

        if($this->getId() === 0 || is_null($this->getId())){
            //New object to save
            $this->id = $indigoStorm->getDb2()->generateObject($this);
        }else{
            //Existing object to update
            $this->handleRevision($revisionType);
            $indigoStorm->getDb2()->updateObject($this);
        }

        $this->connectDataTableSources();

        foreach($this as $propName => $property){
            if(gettype($property) === 'object' && get_class($property) === DATATABLE_CLASS){
                $indigoStorm->getDb2()->saveDataTable($this, $property);
            }
        }
    }

    /**
     * @param $revisionType
     * @throws \Exception
     */
    protected function handleRevision($revisionType){
        //If the revision action is set to overwrite or the object doesn't support it, don't save a revision
        if($revisionType === SAVE_REVISIONS_NO || $this->revisionHandling === SAVE_REVISIONS_NO){
            return;
        }

        $revision = new ObjectRevision();
        switch($revisionType){
            case SAVE_REVISIONS_LOG:
                $revision->generateRevisionLog($this);
                break;
            case SAVE_REVISIONS_OBJECT:
            default:
                $revision->generateRevision($this);
                break;
        }
    }

    protected function connectDataTableSources(){
        foreach($this as $propertyName => $propertyContent){
            if(gettype($propertyContent) === 'object' && get_class($propertyContent) === DATATABLE_CLASS){
                $this->{$propertyName}->connectSource($this->typeof(true), $this->getId());
            }
        }
    }

    protected function _populateObject($id, $name){
        if($this->getId() === 0 || $this->getId() === false || is_null($this->getId())){
            $this->id = intval($id);
            $this->setName($name);
            $this->oldName = $name;
            $this->connectDataTableSources();
        }else{
            throw new \Exception("Cannot populate existing object.", 500);
        }
    }

    /**
     * Basic constructor. If id is defined, a resource will be loaded from the database. If not, a new resource will be
     * created ready for use.
     * @param mixed $id
     * Optional. Either the ID or the name of the resource in its table. If set, this will return the resource from the
     * database. If not, it will generate a new object ready for use.
     * @param mixed $lookupType
     * Optional. Whether to load the object by name or ID (defaults to name). Not all objects support ID and will fall
     * back to name.
     * @throws \Exception
     * If $id is defined and cannot be matched to an existing resource, 404 is thrown.
     */
    function __construct($id = false, $lookupType = SEARCH_BY_NAME){
        global $indigoStorm;

        $this->addDataTable('Metadata', DB2_VARCHAR, DB2_BLOB);
        $this->configure();
        $indigoStorm->getDb2()->registerObject($this->typeof(true), $this);

        if($id !== false && !is_null($id) && $id !== ''){

            //Existing object, load in details
            $details = $this->_getDetails($id, $lookupType);

            if($details !== false){
                $this->_populateObject($details['id'], $details['name']);
            }else{
                throw new \Exception('Resource not found ' . $this->typeof(), 404);
            }

        }
    }

    protected function configure(){
        return false;
    }

    /**
     * Get the type of the current resource.
     *
     * @return string
     * The type of the current resource (namespace independent).
     */
    function typeof($uppercase = false){
        if(isset($this->useClass)){
            $typ = $this->useClass;
        }else{
            $typ = get_class($this);
        }

        $typ = explode("\\", $typ);

        if($uppercase){
            return $typ[count($typ) - 1];
        }else{
            return strtolower($typ[count($typ) - 1]);
        }
    }

    /**
     * Loads the entire resource from the database. If this is called, the object held in PHP will match the resource
     * in the database exactly at the time called.
     */
    function getAll(){

        foreach($this as $propName => $property){
            if(gettype($property) === 'object' && get_class($property) === DATATABLE_CLASS){
                $property->fetchDataTable();
            }
        }

    }

    public function getOldName(){
        return $this->oldName;
    }

    /**
     * Magic method call to get values from the resource. If the value you want hasn't yet been loaded from the database
     * it will be lazy loaded and then returned.
     * @param string $name
     * The name of the call made. This should get "getSomething" where "Something" is the property or dataset from which
     * you want to retrieve data.
     * @param array $args
     * The arguments given in the call. If a dataset is defined as the target for the get, the first argument will be
     * used as the key that you want to get (if not argument is specified, the entire dataset is returned).
     * @return mixed
     * If the value can be found, it is returned. If not, false is returned.
     * @throws \Exception
     * If a DataTable set request is incorrectly formatted, throws 500
     */
    function __call($name, $args){

        $controlString = strtolower(substr($name, 0, 3));
        $remainder = substr($name, 3);

        if(isset($this->{$remainder}) && $remainder !== 'Name' && $remainder !== 'Id'){

            switch ($controlString){
                case 'get':

                    //Get a value from the datatable (null if non-existent) or an entire datatable
                    if(isset($args[0])){
                        return $this->{$remainder}->fetchFromDataTable($args[0]);
                    }else{
                        return $this->{$remainder}->fetchDataTable();
                    }
                    break;

                case 'set':

                    //Set a value in the datatable (either existing or new), or an entire datatable (key/val array)
                    if(isset($args[0]) && count($args) === 2){
                        //Set individual item
                        return $this->{$remainder}->writeToDataTable($args[0], $args[1]);
                    }elseif(isset($args[0]) && is_array($args[0]) && !isset($args[1])){
                        //Replace whole table
                        return $this->{$remainder}->writeDataTable($args[0]);
                    }else{
                        throw new \Exception("Malformed DataTable request (SET) " . $name . " " . json_encode($args), 500);
                    }
                    break;

                case 'add':

                    //Special option to merge a new dataset into an existing datatable (new data is prioritised)
                    if(isset($args[0]) && is_array($args[0])){
                        $oldData = $this->{$remainder}->fetchDataTable();
                        $newData = array_merge($oldData, $args[0]);

                        return $this->{$remainder}->writeDataTable($newData);
                    }
                    break;

                case 'del':

                    // Delete a value from the datatable
                    if (isset($args[0])) {
                       $keyToDelete = trim($args[0]);
                       return $this->{$remainder}->deleteFromDataTable($keyToDelete);
                    } else {
                        foreach ($this->{$remainder}->getExistingKeys() as $existingKey) {
                            $this->{$remainder}->deleteFromDataTable($existingKey);
                        }
                        return true;
                    }
                    break;
            }

        }elseif($remainder === 'Name'){

            switch ($controlString){
                case 'get':
                    return $this->name;
                    break;
                case 'set':
                    if(isset($args[0]) && (is_string($args[0]) || is_numeric($args[0]))){
                        $this->name = $args[0];
                    }else{
                        throw new \Exception("Malformed object request (SET)", 500);
                    }
                    break;
            }

        }elseif($remainder === 'Id'){

            switch ($controlString){
                case 'get':
                    return $this->id;
                    break;
            }

        }else{
            return false;
        }

    }

    /**
     * @return array A key/value array of revisions (revision name => overwrite time) for the object that contain
     * recoverable content sorted by the time the content was overwritten.
     */
    public function listRevisions(){
        global $indigoStorm;

        $listOfRevisions = $indigoStorm->getDb2()->searchForObjects('ObjectRevision', 'Metadata',
            array(
                'objectClass' => array('eq' => get_class($this)),
                'objectId' => array('eq' => $this->getId()),
                'canRecover' => array('eq' => true)
            ));

        $revisions = array();

        foreach($listOfRevisions as $revisionId){
            try{
                $revision = new ObjectRevision($revisionId);
                $revisions[$revisionId] = floatval($revision->getMetadata('overwriteTime'));
                unset($revision);
            }catch(\Exception $e){
                continue;
            }
        }

        asort($revisions);

        return $revisions;
    }

    /**
     * Immediately recover an old revision of the object. Use listRevisions() to get valid revisions.
     *
     * @param $revisionName string The name of the revision to recover from
     * @throws \Exception If the revision is not found or not valid, an error will be thrown
     */
    public function recoverRevision($revisionName){
        $revision = new ObjectRevision($revisionName);

        if(
            $revision->getMetadata('objectClass') !== get_class($this) ||
            $revision->getMetadata('objectId') != $this->getId() ||
            $revision->getMetadata('canRecover') != true
        ){
            throw new \Exception('Revision not valid for object or cannot recover', 500);
        }

        foreach($revision->getContent() as $key => $content){
            if($key === 'name'){
                $this->name = $content;
            }else{
                $this->{'set' . $key}($content);
            }
        }

        $this->persist();
        $this->getAll();

    }

    /**
     * Delete the object from the database.
     * @param mixed $backupType Whether to keep or delete the backup. Defaults to the object type's preset behaviour.
     */
    public function delete($backupType = null){
        global $indigoStorm;

        if(is_null($backupType)){
            $backupType = $this->defaultBackupActivity;
        }
        $allowedDestructions = array(DELETE_BACKUP_NEVER, DELETE_BACKUP_7D, DELETE_BACKUP_30D, DELETE_NOBACKUP);
        if(!in_array($backupType, $allowedDestructions)){
            $backupType = DELETE_BACKUP_NEVER;
        }

        $indigoStorm->getDb2()->deleteObject($this, $backupType);

    }

    /**
     * Create a packaged array of the object without unneeded items or DataTable depth. Format supported by `->unpack()`
     * @param null|array $datasets
     * @return array The packaged version of the object
     */
    public function pack($datasets = null){
        $package = array();

        if(is_array($datasets)){
            $datasets = array_map('strtoupper', $datasets);
        }

        $packageKeys = array('name', 'id');

        foreach(get_object_vars($this) as $objectVarKey => $objectVar){

            if(in_array($objectVarKey, $packageKeys)){
                //Key should be packaged
                $package[$objectVarKey] = $objectVar;
            }elseif(is_object($objectVar) && get_class($objectVar) === DATATABLE_CLASS){
                //Key is a DataTable, package the data (if requested)
                if((!is_null($datasets) && in_array(strtoupper($objectVarKey), $datasets)) || is_null($datasets)){
                    $package[$objectVarKey] = $objectVar->fetchDataTable();
                }
            }

        }

        return $package;

    }

    /**
     * Unpack an associative array (or json-encoded string) into the object. Format produced by `->pack()`
     * @param string|array $package The array or json-encoded array of the target object content
     * @throws \Exception Exception thrown if package is invalid
     */
    public function unpack($package){

        if(is_string($package)){
            $package = json_decode($package, true);
        }

        if(!is_array($package)){
            throw new \Exception("Invalid package, must be array", 500);
        }

        // Load everything in before unpacking new content
        if($this->id){
            $this->getAll();
        }

        $packageKeys = array('name');

        foreach(get_object_vars($this) as $objectVarKey => $objectVar){

            $objectVarKeyUc = ucwords($objectVarKey);

            if(in_array($objectVarKey, $packageKeys)){

                //Key can be edited by package
                if(array_key_exists($objectVarKey, $package)){
                    $this->{'set' . $objectVarKeyUc}($package[$objectVarKey]);
                }

            }elseif(is_object($objectVar) && get_class($objectVar) === DATATABLE_CLASS){

                //Key is a DataTable, fill table
                if(array_key_exists($objectVarKey, $package)){

                    $this->{'set' . $objectVarKey}($package[$objectVarKey]);
                }

            }

        }

    }

    /**
     * Load an object or create a new object with the name (if provided) if one doesn't already exist. Can be used to
     * instantiate objects by then calling ->persist() or to confirm if an object exists without requiring a try/catch.
     * @param mixed $id The name or ID of the object to be loaded.
     * @param string $lookupType Whether to look up the object by name or ID.
     * @return bool Whether the object was loaded (true) or created (false). NB: creation does not persist the object.
     * @throws \Exception
     */
    public function loadOrCreate($id, $lookupType = SEARCH_BY_NAME) {
        $details = $this->_getDetails($id, $lookupType);

        if ($details === false && $lookupType === SEARCH_BY_NAME) {
            $this->setName($id);
        } elseif ($details !== false) {
            $this->_populateObject($details['id'], $details['name']);
        }

        return $details !== false;
    }

    protected function _getDetails($id, $lookupType = SEARCH_BY_NAME) {
        global $indigoStorm;
        if (!$this->allowEnumeration || ($lookupType !== SEARCH_BY_NAME && $lookupType !== SEARCH_BY_ID)) {
            $lookupType = SEARCH_BY_NAME;
        }
        if($lookupType === SEARCH_BY_NAME){
            $foundId = $indigoStorm->getDb2()->findObject($this->typeOf(true), $id, $lookupType);
        }else{
            $foundId = $id;
        }

        $details = $indigoStorm->getDb2()->getObject($this->typeof(true), $foundId);
        if (is_null($details)) {
            return false;
        } else {
            return $details;
        }

    }

}

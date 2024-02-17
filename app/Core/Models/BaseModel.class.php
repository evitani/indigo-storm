<?php

namespace Core\Models;

use Core\Db2\Models\DataTable;

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
     * @var bool Whether ID lookup should be allowed by the object
     */
    protected $allowEnumeration = false;

    protected function addDataTable($tableName, $keyDetails, $valueDetails){
        if($this->getId() === 0 || is_null($this->getId())){
            $newObject = true;
        }else{
            $newObject = false;
        }
        $this->{$tableName} = new DataTable($tableName, $keyDetails, $valueDetails, $newObject);
    }

    public function persist(){
        global $Application;

        if($this->getId() === 0 || is_null($this->getId())){
            //New object to save
            $this->id = $Application->db2->generateObject($this);
        }else{
            //Existing object to update
            $Application->db2->updateObject($this);
        }

        $this->connectDataTableSources();

        foreach($this as $propName => $property){
            if(gettype($property) === 'object' && get_class($property) === DATATABLE_CLASS){
                $Application->db2->saveDataTable($this, $property);
            }
        }
    }

    protected function connectDataTableSources(){
        foreach($this as $propertyName => $propertyContent){
            if(gettype($propertyContent) === 'object' && get_class($propertyContent) === DATATABLE_CLASS){
                $this->{$propertyName}->connectSource($this->typeof(true), $this->getId());
            }
        }
    }

    protected function populateObject($id, $name){
        if($this->getId() === 0 || $this->getId() === false || is_null($this->getId())){
            $this->id = intval($id);
            $this->setName($name);
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
     * @throws \Exception
     * If $id is defined and cannot be matched to an existing resource, 404 is thrown.
     */
    function __construct($id = false, $forceIdLookup = false){
        global $Application;

        //Activate Db2 in the event this is the first time it's been used in this call
        if(is_null($Application->db2)){
            $Application->initDb2();
        }

        $this->addDataTable('Metadata', DB2_VARCHAR, DB2_BLOB);
        $this->configure();
        $Application->db2->registerObject($this->typeof(true), $this);

        if($id !== false && !is_null($id) && $id !== ''){

            //Existing object
            if($forceIdLookup === false || $this->allowEnumeration === false){
                $forceIdLookup = SEARCH_BY_NAME;
            }

            if($forceIdLookup === SEARCH_BY_NAME || $forceIdLookup === SEARCH_BY_ID){

                if($forceIdLookup === SEARCH_BY_NAME){
                    $foundId = $Application->db2->findObject($this->typeOf(true), $id, $forceIdLookup);
                }else{
                    $foundId = $id;
                }
                if($foundId !== false){
                    //Found the object, populate.

                    $details = $Application->db2->getObject($this->typeof(true), $foundId);

                    if($details !== false){
                        $this->populateObject($details['id'], $details['name']);
                    }else{
                        throw new \Exception('Resource not found ' . $this->typeof(), 404);
                    }

                }else{
                    //No matching object was found.
                    throw new \Exception('Resource not found ' . $this->typeof(), 404);
                }
            }else{
                throw new \Exception('Malformed Object Fetch', 500);
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
                        throw new \Exception("Malformed DataTable request (SET)", 500);
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

                    // @TODO design a deletion method
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
}

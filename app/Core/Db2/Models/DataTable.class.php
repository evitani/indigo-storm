<?php

namespace Core\Db2\Models;

class DataTable{
    public $data = array();
    public $schema;
    protected $edited = false;
    protected $name;
    public $dataSourceObject;
    public $dataSourceId;
    protected $loaded = false;
    private $dataSourceSet = false;
    private $existingKeys = array();

    public function __construct($tableName, $keyDetails, $valueDetails, $newObject = false){
        $this->schema = array(
            'key' => $keyDetails,
            'val' => $valueDetails,
        );
        $this->name = $tableName;
        if($newObject){
            $this->loaded = true;
        }
    }

    public function fetchFromDataTable($key){
        $this->lazyLoad();
        if(key_exists($key, $this->data)){
            return $this->data[$key];
        }else{
            return null;
        }
    }

    public function fetchDataTable(){
        $this->lazyLoad();

        return $this->data;
    }

    public function writeToDataTable($key, $value){
        $this->data[$key] = $value;
        $this->edited = true;

        return true;
    }

    public function writeDataTable($table){
        if(is_array($table)){
            $this->data = $table;
            $this->edited = true;

            return true;
        }else{
            return false;
        }
    }

    public function emptyDataTable(){
        $this->data = array();
        $this->edited = true;

        return true;
    }

    public function getName(){
        return $this->name;
    }

    public function connectSource($objectName, $objectId){
        if($this->dataSourceSet === false && $objectId > 0){
            $this->dataSourceObject = $objectName;
            $this->dataSourceId = $objectId;
            $this->dataSourceSet = true;
            $this->loaded = false;

            return true;
        }
    }

    private function lazyLoad(){
        global $indigoStorm;

        if(!$this->loaded){
            $fromSource = $indigoStorm->getDb2()->getDataTable($this->dataSourceObject, $this->dataSourceId, $this);
            if(is_array($fromSource)){
                $formattedData = array();
                $this->existingKeys = array();
                foreach($fromSource as $item){
                    $formattedData[$item['dataKey']] = $item['dataValue'];
                    array_push($this->existingKeys, $item['dataKey']);
                }
                if(count($formattedData) > 0){
                    $this->data = $formattedData;
                }
            }
            $this->loaded = true;
        }
    }

    public function getExistingKeys(){
        return $this->existingKeys;
    }

}

<?php

namespace Core\Models;

class ObjectRevision extends BaseModel{

    protected $revisionHandling = SAVE_REVISIONS_NO;

    public function configure(){
        $this->addDataTable('Content', DB2_VARCHAR, DB2_BLOB_LONG);
    }

    private function generateName($object){
        $salt = get_class($object) . $object->getId();
        return sha1(uniqid($salt, true));
    }

    private function loadObject($class, $name){
        return new $class($name);
    }

    public function generateRevisionLog($object){
        $this->setName($this->generateName($object));
        $this->setMetadata('objectClass', get_class($object));
        $this->setMetadata('objectId', $object->getId());
        $this->setMetadata('overwriteTime', microtime(true));
        $this->setMetadata('canRecover', false);
        $this->persist();
    }

    public function generateRevision($object){

        $oldName = $object->getOldName();

        if(is_null($oldName)){
            return;
        }

        $this->setName($this->generateName($object));
        $this->setMetadata('objectClass', get_class($object));
        $this->setMetadata('objectId', $object->getId());
        $this->setMetadata('overwriteTime', microtime(true));
        $this->setMetadata('canRecover', true);

        $dbCopy = $this->loadObject(get_class($object), $object->getOldName());
        $dbCopy->getAll();

        foreach(get_object_vars($dbCopy) as $objectVar => $varContent){
            if($objectVar === 'name'){
                $this->setContent($objectVar, $varContent);
            }elseif(is_object($varContent)){
                $this->setContent($objectVar, $varContent->data);
            }
        }

        unset($dbCopy);

        $this->persist();

    }
}

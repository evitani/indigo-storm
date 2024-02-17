<?php

namespace Core\Models;

class RequestTree extends BaseModel{
    protected $db2 = true;

    protected $currentInteraction = null;

    public function configure(){
        $this->addDataTable('Interaction', DB2_VARCHAR, DB2_BLOB_LONG);
        $this->addDataTable('Exception', DB2_VARCHAR_SHORT, DB2_BLOB_LONG);
    }

    public function startTree($entryPoint){
        $this->setName(sha1(uniqid('IS', true)));
        syslog(LOG_INFO, 'New Tree started with ID: ' . $this->getName());

        $trimmingEntryPoint = explode("?", $entryPoint);
        if(count($trimmingEntryPoint) > 1){
            $queryParams = array();
            foreach(explode("&", $trimmingEntryPoint[1]) as $queryItem){
                $heldItems = explode("=", $queryItem);
                if(count($heldItems) > 1){
                    $queryParams[$heldItems[0]] = $heldItems[1];
                }else{
                    $queryParams[$heldItems[0]] = true;
                }
            }
            if(array_key_exists('apiKey', $queryParams)){
                unset($queryParams['apiKey']);
            }
            if(count($queryParams) > 0){
                $this->setMetadata('queryParams', $queryParams);
            }
        }

        $fullEntryPoint = $trimmingEntryPoint[0];

        $explodedEntryPoint = explode("/", $fullEntryPoint);
        if(count($explodedEntryPoint) > 3){
            $entryPointArguments = array_slice($explodedEntryPoint, 3);
            $entryPoint = array_slice($explodedEntryPoint, 0, 3);
            $entryPoint = implode("/", $entryPoint);
            $this->setMetadata('entryPoint', $entryPoint);
            $this->setMetadata('arguments', $entryPointArguments);
        }else{
            $this->setMetadata('entryPoint', $fullEntryPoint);
        }

        $this->setMetadata('startTime', microtime(true));
        $this->persist();
    }

    public function endTree($statusCode = 200){
        if($statusCode !== 200){
            $this->setMetadata('statusCode', $statusCode);
        }
        $this->setMetadata('endTime', microtime(true));
        $this->persist();
    }

    public function logException($errno, $errMessage){
        $this->getAll();
        $this->setException(strval(microtime(true)),
                            array(
                                'errorCode'    => $errno,
                                'errorMessage' => $errMessage,
                                'interaction'  => $this->currentInteraction,
                            ));
        if(count($this->getInteraction()) == 1){
            $this->endTree($errno);
        }else{
            $this->persist();
        }
    }

    public function setCurrentInteraction($currentInteraction){
        if(is_null($this->currentInteraction)){
            $this->currentInteraction = $currentInteraction;
        }
    }

    public function getCurrentInteraction(){
        return $this->currentInteraction;
    }

}

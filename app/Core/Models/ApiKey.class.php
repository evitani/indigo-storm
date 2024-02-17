<?php

namespace Core\Models;

class ApiKey extends BaseModel{
    protected $db2 = true;

    public function configure(){
        $this->addDataTable('License', DB2_VARCHAR, DB2_VARCHAR);
    }

    public function getUsage($startTime = null, $endTime = null){
        global $Application;

        $searchArray = array('apiKey' => array('eq' => $this->getId()));

        if(!is_null($startTime)){
            $searchArray['startTime'] = array('gt' => strval($startTime - 0.01));
        }

        if(!is_null($endTime)){
            if(!is_null($startTime)){
                $searchArray['startTime']['lt'] = strval($endTime + 0.01);
            }else{
                $searchArray['startTime'] = array('lt' => strval($endTime + 0.01));
            }
        }

        $listOfInteractions = $Application->db2->searchForObjects('RequestTree', 'Metadata', $searchArray);

        $processingTime = 0;

        foreach($listOfInteractions as $interactionName){
            $interaction = new RequestTree($interactionName);
            $startTime = floatval($interaction->getMetadata('startTime'));
            $endTime = floatval($interaction->getMetadata('endTime'));
            if($endTime > 0 && $startTime > 0){
                $timeSpent = $endTime - $startTime;
                $processingTime += $timeSpent;
            }
        }

        if($processingTime > 0){
            $processingTime = intval($processingTime) + 1;
        }

        return $processingTime;

    }

    public function calculateInvoice($startTime = null, $endTime = null){
        $hourlyCost = floatval($this->getMetadata('hourlyCost'));
        if($hourlyCost > 0){
            $cost = $this->getUsage($startTime, $endTime) * (($hourlyCost / 60) / 60);
            $cost = round($cost, 2);

            return $cost;
        }else{
            return false;
        }
    }

}

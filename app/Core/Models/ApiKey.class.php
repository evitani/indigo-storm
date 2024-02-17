<?php

namespace Core\Models;

use Core\Db2\Models\SearchQuery;

class ApiKey extends BaseModel{
    protected $db2 = true;

    public function configure(){
        $this->addDataTable('License', DB2_VARCHAR, DB2_VARCHAR);
    }

    public function getUsage($startTime = null, $endTime = null){

        $search = new SearchQuery(new RequestTree());
        $search->filter($search->_and(
            ['Metadata.apiKey', '=', $this->getId()]
        ));

        $filters = array(
            ['Metadata.apiKey', '=', $this->getId()]
        );

        if(!is_null($startTime)){
            array_push($filters, ['Metadata.startTime', '>', strval($startTime - 0.01)]);
        }

        if(!is_null($endTime)){
            array_push($filters, ['Metadata.startTime', '<', strval($endTime + 0.01)]);
        }

        if (count($filters) > 1) {
            $listOfInteractions = $search->filter($search->_and($filters));
        } else {
            $listOfInteractions = $search->filter($filters)->run();
        }

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

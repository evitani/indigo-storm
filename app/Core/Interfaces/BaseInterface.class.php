<?php

namespace Core\Interfaces;

class BaseInterface{

    protected $baseService = null;
    protected $serviceUrl = null;

    protected function getService(){

        if(is_null($this->baseService)){
            $currentClass = explode("\\", get_class($this));
            $this->baseService = $currentClass[1];
            $this->baseService = strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', $this->baseService));
        }

        return $this->baseService;
    }

    protected function getServiceUrl($endpoint = null){

        if(is_null($this->serviceUrl)){
            global $indigoStorm;

            $serviceName = $this->getService();
            $serviceUrl = $indigoStorm->getConfig('url');
            $serviceUrl = str_replace('_SERVICE_', $serviceName, $serviceUrl);
            $this->serviceUrl = $serviceUrl . '/';
        }

        if(!is_null($endpoint)){
            return $this->serviceUrl . urlencode($endpoint);
        }else{
            return $this->serviceUrl;
        }

    }


    protected function sendGet($endpoint, $args){

        $url = $this->getServiceUrl($endpoint);

        if(!is_array($args) && !is_null($args) && $args !== ''){
            $args = array($args);
        }

        foreach($args as $arg){
            $url .= "/" . urlencode($arg);
        }

        $response = $this->curlExec([
                                        CURLOPT_URL => $url,
                                    ]);

        return $response;
    }

    protected function sendPost($endpoint, $payload){

        $url = $this->getServiceUrl($endpoint);

        $response = $this->curlExec([
                                        CURLOPT_URL        => $url,
                                        CURLOPT_POST       => count($payload),
                                        CURLOPT_POSTFIELDS => http_build_query($payload),

                                    ]);

        return $response;
    }

    protected function sendPut($endpoint, $id, $payload){
        $url = $this->getServiceUrl($endpoint) . '/' . urlencode($id);

        $response = $this->curlExec([
                                        CURLOPT_URL           => $url,
                                        CURLOPT_CUSTOMREQUEST => "PUT",
                                        //            CURLOPT_HTTPHEADER => [$this->getTreeHeader(), $this->getEnvironmentHeader(), 'X-Http-Method-Override: PUT'],
                                        CURLOPT_POST          => count($payload),
                                        CURLOPT_POSTFIELDS    => http_build_query($payload),
                                    ]);

        return $response;
    }

    protected function getTreeHeader(){
        global $indigoStorm;
        $tree = $indigoStorm->getRouter()->getRequest()->getTree();

        return 'is-request-tree: ' . $tree->getName();
    }

    protected function getEnvironmentHeader(){
        global $indigoStorm;

        return 'is-identity: ' . implode('-', array(
            $indigoStorm->getConfig('env'), $indigoStorm->getConfig('tier')
            ));
    }

    protected function formatResponse($response){
        if(is_array(json_decode($response, true)) || is_object($response, true)){

            $heldResponse = json_decode($response, false);


            foreach($heldResponse as $itemName => $itemContent){
                if(is_object($itemContent) && isset($itemContent->data)){
                    $heldDataItems = $itemContent->data;
                    if(is_array($heldDataItems)){
                        $heldResponse->{$itemName}->data = new \stdClass();
                        foreach($heldDataItems as $heldItemId => $heldItemContent){
                            $heldResponse->{$itemName}->data->{$heldItemId} = $heldItemContent;
                        }
                    }else{
                        $heldResponse->{$itemName}->data = $heldDataItems;
                    }
                }
            }

            return $heldResponse;
        }else{
            return $response;
        }
    }

    protected function curlExec($curlSetup){
        $curl = curl_init();
        $standardCurlSetup = array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [$this->getTreeHeader(), $this->getEnvironmentHeader()],
        );

        $combinedCurlSetup = $standardCurlSetup;

        foreach($curlSetup as $curlId => $curlContent){
            $combinedCurlSetup[$curlId] = $curlContent;
        }

        curl_setopt_array($curl, $combinedCurlSetup);
        try{
            $response = curl_exec($curl);
        }catch (\Exception $e){

        }

        if($response === false){
            throw new \Exception('Service Communication Error ' . curl_error($curl), 500);
        }elseif(curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200){
            throw new \Exception("[SERVICE ERROR] " . $response, curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
        }else{
            return $this->formatResponse($response);
        }
    }

}

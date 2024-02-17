<?php

namespace Core\Tasks\Helpers;

class ServiceHelper{

    public function getUrl($service, $endpoint){
        global $indigoStorm;

        $url = $indigoStorm->getConfig('url');

        $url = str_replace("_SERVICE_", $this->getServiceName($service), $url);

        if(substr($url, -1) !== '/'){
            $url .= "/";
        }

        if(substr($endpoint, 0, 1) === '/'){
            $endpoint = substr($endpoint, 1);
        }

        $url = $url . $endpoint;

        return $url;

    }

    public function validateMethod($method){
        $allowedMethods = array('GET','POST','PUT','DELETE');
        $method = strtoupper($method);

        if(in_array($method, $allowedMethods)){
            return $method;
        }else{
            throw new \Exception('Invalid push task method', 500);
        }
    }

    public function generateGetPayload($payload){
        $str = '';
        foreach($payload as $item){
            $str .= '/' . urlencode($item);
        }

        return $str;
    }

    public function generateQueryString($query){
        if(count($query) > 0){
            $str = '?';
            $first = true;
            foreach($query as $key => $item){
                if($first){
                    $first = false;
                }else{
                    $str .= "&";
                }
                $str .= $key . '=' . urlencode($item);
            }

            return $str;
        }else{
            return '';
        }
    }

    public function canSend(){
        return getenv('GAE_APPLICATION') !== false;
    }

    public function standardHeaders($type, $taskName){
        global $indigoStorm;

        if(!in_array($type, array('PushTask', 'ScheduledTask', 'Task'))){
            $type = 'Task';
        }

        $envDetails = array(
            $indigoStorm->getConfig('env'),
            $indigoStorm->getConfig('tier'),
        );

        $headers = array(
            'is-request-type' => $type,
            'is-identity' => $envDetails[0] . '-' . $envDetails[1],
            'is-request-tree' => $indigoStorm->getRouter()->getRequest()->getTree()->getName(),
            'is-task-id' => $taskName,
        );

        if(strpos(_RUNNINGSERVICE_, ',') === false){
            $headers['is-request-source'] = _RUNNINGSERVICE_;
        }

        return $headers;
    }

    public function generateName($url, $method){
        global $indigoStorm;
        $base = $url . $method . $indigoStorm->getConfig('security')['globalSalt'];
        return sha1(uniqid($base, true));
    }

    private function getServiceName($service){

        preg_match_all('/((?:^|[A-Z])[a-z\-0-9]+)/',$service,$matches);

        $str = '';

        foreach($matches[0] as $match){
            $str .= '-' . $match;
        }

        $str = substr(strtolower($str), 1);

        return $str;

    }

}

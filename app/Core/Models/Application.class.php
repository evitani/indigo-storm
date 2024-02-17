<?php

namespace Core\Models;

use Core\Db2\Models\Database2;

class Application{

    private $hasEnvironment = false;

    private $environment = array();
    private $database = array();
    private $services = array();
    private $middleware = array();
    public $tree = null;
    protected $environmentDetails;
    public $user;
    public $calledByInterface = false;
    protected $endpointSecurity = array();
    protected $endpointAccess = array();
    public $key = null;

    public $db2 = null;

    public function initDb2(){
        $this->db2 = new Database2($this->database);
    }

    public function getEnvironmentDetails(){
        return $this->environmentDetails;
    }

    public function setEnvironment($envDetails){
        if($this->hasEnvironment === false){
            if(is_array($envDetails) && count($envDetails) === 2){

                if(stream_resolve_include_path('config/' . $envDetails[0]) !== false){

                    $loadedConfig = array();

                    if(stream_resolve_include_path('config/global.config.php') !== false){
                        include 'config/global.config.php';
                        $loadedConfig = array_merge_recursive($loadedConfig, $globalEnvironmentConfig);
                    }

                    if(stream_resolve_include_path('config/' . $envDetails[0] . '/shared.config.php') !== false){
                        include 'config/' . $envDetails[0] . '/shared.config.php';
                        $loadedConfig = array_merge_recursive($loadedConfig, $sharedEnvironmentConfig);
                    }

                    if(stream_resolve_include_path('config/' . $envDetails[0] . '/' . $envDetails[1] . '.config.php') !== false){
                        include 'config/' . $envDetails[0] . '/' . $envDetails[1] . '.config.php';
                        $loadedConfig = array_merge_recursive($loadedConfig, $environmentConfig);
                    }

                    if($this->checkConfigSyntax($loadedConfig)){
                        $this->setEnvironmentConfig($loadedConfig);

                        $this->hasEnvironment = true;
                        $this->environmentDetails = $envDetails;
                    }else{
                        throw new \Exception('Invalid environment structure.', 500);
                    }

                }

            }else{
                throw new \Exception('Invalid environment structure.', 500);
            }
        }else{
            throw new \Exception('Environment already set and cannot be changed.', 500);
        }
    }

    private function setEnvironmentConfig($config){
        $this->database = $config['database'];
        unset($config['database']);
        $this->services = $config['services'];
        unset($config['services']);
        if(array_key_exists('middleware', $config)){
            $this->middleware = $config['middleware'];
            unset($config['middleware']);
        }

        if(array_key_exists('locales', $config) && is_array($config['locales'])){

            if(_LOCALE_ == 'default' && array_key_exists('defaultLocale', $config)){
                $locale = $config['defaultLocale'];
            }else{
                $locale = _LOCALE_;
            }

            if(in_array($locale, $config['locales'])){
                $config['url'] = str_replace("_LOCALE_", $locale, $config['url']);
            }

        }

        $this->environment = $config;
    }

    private function checkConfigSyntax($config){
        $requiredEntries = array(
            'name'     => array('type' => 'string'),
            'database' => array('type' => 'array', 'minLength' => 4),
            'services' => array('type' => 'array', 'minLength' => 1),
        );

        foreach($requiredEntries as $rKey => $rVal){
            if(array_key_exists($rKey, $config)){
                foreach($rVal as $rSubKey => $rSubVal){
                    switch ($rSubKey){
                        case 'type':
                            switch ($rSubVal){
                                case 'string':
                                    if(!is_string($config[$rKey])){
                                        return false;
                                    }
                                    break;
                                case 'array':
                                    if(!is_array($config[$rKey])){
                                        return false;
                                    }
                                    break;
                            }
                            break;
                        case 'minLength':
                            if(count($config[$rKey]) < $rSubVal){
                                return false;
                            }
                            break;
                    }
                }
            }
        }

        return true;
    }

    public function getEnvironmentVariable($variable){
        if(array_key_exists($variable, $this->environment)){
            return $this->environment[$variable];
        }else{
            throw new \Exception('Environment variable not found.', 500);
        }
    }

    public function getMiddleware(){
        $workingMiddleware = array();
        foreach($this->middleware as $mwService => $mwClasses){

            foreach($mwClasses as $mwClass){
                $mwClassNameLoad = explode(':', $mwClass);
                if(count($mwClassNameLoad) == 2){

                    $mwClassName = explode('/', $mwClassNameLoad[0]);

                    $mwClassNameLast = array_pop($mwClassName);
                    $mwClassName[] = "Middleware";
                    $mwClassName[] = $mwClassNameLast;

                    $workingMiddleware[] = array($mwService . "\\" . implode('\\', $mwClassName),
                                                 intval($mwClassNameLoad[1]),
                    );

                }else{
                    throw new \Exception('Could not parse middleware instructions.', 500);
                }
            }
        }

        if(count($workingMiddleware) > 1){
            usort($workingMiddleware, function($a, $b){
                return $b[1] - $a[1];
            });
            $sortedMiddleware = array();
            foreach($workingMiddleware as $workingMw){
                $sortedMiddleware[] = $workingMw[0];
            }

            return $sortedMiddleware;
        }elseif(count($workingMiddleware) == 1){
            return array($workingMiddleware[0][0]);
        }else{
            return array();
        }
    }

    public function getServices($serviceName = null){
        if(is_null($serviceName)){
            return $this->services;
        }elseif(array_key_exists($serviceName, $this->services)){
            return $this->services[$serviceName];
        }else{
            return array();
        }
    }

    public function setAuthentication($serviceName, $authenticationArray){
        if(array_key_exists($serviceName, $this->services)){
            foreach($authenticationArray as $authServiceName => $authServiceDetails){
                if(array_key_exists($authServiceName, $this->services[$serviceName]) && is_null($this->services[$serviceName][$authServiceName])){
                    $this->services[$serviceName][$authServiceName] = $authServiceDetails;
                }
            }
        }
    }

    public function setTree($tree){
        $this->tree = $tree;
    }

    public function setUser($userId){
        $this->user = $userId;
    }

    public function setKey($key){
        if(is_null($this->key)){
            $this->key = $key;
        }
    }

    public function enableEndpointSecurity($endpoint, $method){
        if(array_key_exists($endpoint, $this->endpointSecurity) && is_array($this->endpointSecurity[$endpoint])){
            $this->endpointSecurity[$endpoint][$method] = true;
        }else{
            $this->endpointSecurity[$endpoint] = array($method => true);
        }

        return true;
    }

    public function isEndpointSecure($endpoint, $method){
        $method = strtolower($method);
        if(array_key_exists($endpoint, $this->endpointSecurity) && is_array($this->endpointSecurity[$endpoint])){
            if(array_key_exists($method, $this->endpointSecurity[$endpoint])){
                return $this->endpointSecurity[$endpoint][$method];
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    public function enableEndpointAccessControl($endpoint, $method, $restriction){
        $method = strtolower($method);
        if(array_key_exists($endpoint, $this->endpointAccess) && is_array($this->endpointAccess[$endpoint])){
            $this->endpointAccess[$endpoint][$method] = $restriction;
        }else{
            $this->endpointAccess[$endpoint] = array($method => $restriction);
        }

    }

    public function isEndpointAccessControlled($endpoint, $method){
        $method = strtolower($method);

        if(array_key_exists($endpoint, $this->endpointAccess) && is_array($this->endpointAccess[$endpoint])){

            if(array_key_exists($method, $this->endpointAccess[$endpoint])){
                return $this->endpointAccess[$endpoint][$method];
            }else{
                return false;
            }

        }else{
            return false;
        }
    }

}

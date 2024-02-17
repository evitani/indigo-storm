<?php

namespace Core\Application;

use Core\Db2\Models\Database2;
use Core\Models\RequestTree;
use Core\Routing\Router;

class IndigoStorm {

    private $services = array();
    private $middleware = array();
    private $router;

    private $name;
    private $url;
    private $config = array();

    private $env;
    private $tier;

    private $calledByInterface = null;
    private $host = null;

    private $db2 = null;

    function __construct(){
//        $this->_configure();
//        $this->router = new Router();
    }

    public function getHost($service) {
        if (!is_null($this->host)) {
            return $this->host;
        } else {
            $remove = array('https://', 'http://');
            $host = str_replace($remove, '',$this->url);
            $host = explode('/', $host);
            $host = $host[0];
            if (strpos($host, '_SERVICE_') !== false) {
                $host = str_replace('_SERVICE_', $service, $host);
            }
            $this->host = $host;
            return $host;
        }
    }

    public function registerService($serviceName) {
        $this->services[$serviceName] = new Service($serviceName);
    }

    public function registerMiddleware($middleware, $priority) {
        if (!array_key_exists($middleware, $this->middleware)) {
            $this->middleware[$middleware] = intval($priority);
        } else {
            $this->_handleError(new \Exception("Cannot register same middleware multiple times", 500));
        }
    }

    public function run() {

        $this->_configure();
        $this->router = new Router();

        $lastValue = null;
        foreach ($this->middleware as $key => $value) {
            if (!is_null($lastValue)) {
                if ($value === $lastValue) {
                    islog(LOG_WARNING, "Multiple middleware registered with identical priority");
                }
            }
            $lastValue = $value;
        }

        try{
            $this->router->handle($this->services, $this->middleware);
            if (!is_null($this->db2)){
                $this->db2->close();
            }
        } Catch (\Exception $e) {
            $this->_handleError($e);
        }

    }

    public function getDb2() {
        return $this->db2;
    }

    public function calledByInterface() {
        if (is_null($this->calledByInterface)) {
            $this->calledByInterface = _LITEMODE_ !== true &&
                array_key_exists('HTTP_IS_REQUEST_TREE', $_SERVER) &&
                trim($_SERVER['HTTP_IS_REQUEST_TREE']) !== '';
        }
        return $this->calledByInterface;
    }

    public function getConfig($config) {
        if (property_exists($this, $config)) {
            return $this->$config;
        } elseif (array_key_exists($config, $this->config)) {
            return $this->config[$config];
        } else {
            return null;
        }
    }

    public function getRouter() {
        return $this->router;
    }

    private function _handleError(\Exception $exception = null) {

        if (is_null($exception)) {
            $message = "Unknown error";
            $code = 500;
            $ref = null;
        } else {
            $message = $exception->getMessage();
            $code = intval($exception->getCode());
            $ref = $this->_reportError($exception);
        }

        // Try to close the database connection
        try {
            if (!is_null($this->db2)) {
                $this->db2->close();
            }
        } catch (\Exception $e) { }

        // Set headers to the correct content type and allowing all origins/headers
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: *");

        // Set the response code
        http_response_code($code);

        // Return the error message and a unique reference (if possible)
        $errorResponse = array("message" => $message);
        if (!is_null($ref)) {
            $errorResponse['ref'] = $ref;
        }

        if (_DEVMODE_ === true) {
            $errorResponse['file'] = $exception->getFile();
            $errorResponse['line'] = $exception->getLine();
            $errorResponse['trace'] = $exception->getTrace();
        }

        echo json_encode($errorResponse);
        exit;
    }

    private function _reportError($exception) {
        try {
            if (!is_null($this->router) && !is_null($this->router->getRequest())) {
                $tree = $this->router->getRequest()->getTree();
                if (!is_null($tree)){
                    $currentInteraction = $this->router->getRequest()->getTree()->getCurrentInteraction();
                    $this->router->getRequest()->setTree(
                        new RequestTree($this->router->getRequest()->getTree()->getName())
                    );
                    $this->router->getRequest()->getTree()->setCurrentInteraction($currentInteraction);
                    return $this->router->getRequest()->getTree()->logException(
                        $exception->getCode(),
                        $exception->getMessage()
                    );
                }
                return null;
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function _configure() {

        // Global defaults
        $global = array(
            'devmode' => false,
            'lite' => false,
        );

        try{
            if (file_exists('config/global.ser')) {
                $global = unserialize(file_get_contents('config/global.ser'));
            } elseif (file_exists('config/global.yaml')) {
                $global = array_merge($global, yaml_parse_file('config/global.yaml'));
            }
        } catch (\Exception $e) {
            $this->_handleError($e);
        }

        if ($global['devmode']) {
            define('_DEVMODE_', true);
        } else {
            define('_DEVMODE_', false);
        }

        if ($global['lite']) {
            define('_LITEMODE_', true);
        } else {
            define('_LITEMODE_', false);
        }

        $envAndTier = $this->_getEnvAndTier();
        $this->env = $envAndTier['env'];
        $this->tier = $envAndTier['tier'];

        if (_DEVMODE_){
            $config = $this->_otfConfigImport($this->env, $this->tier);
        } else {
            $config = $this->_releaseConfigImport($this->env, $this->tier);
        }
        $this->_setConfigItems($config);

        if (_LITEMODE_ === false){
            try {
                $this->db2 = new Database2($config['database']);
            } catch (\Exception $e) {
                $this->_handleError($e);
            }
        }

        if (array_key_exists('service', $global)) {
            $this->registerService($global['service']);
        } else {

            foreach ($this->_otfServiceList($config['services']) as $service) {
                $this->registerService($service);
            }
        }

        $middleware = array();
        if (array_key_exists('middleware', $config)) {
            $middleware = $config['middleware'];
        }
        $middleware = $this->_listMiddleware($middleware);
        foreach ($middleware as $priority => $middlewareItem) {
            $this->registerMiddleware($middlewareItem, $priority);
        }

        return null;

    }

    private function _listMiddleware($middleware = array()) {

        if (!_LITEMODE_) {
            $globalMiddleware = array(
                'Core\\Middleware\\ContinuityMiddleware',
                'Core\\Middleware\\KeyMiddleware',
                'Core\\Middleware\\CorsMiddleware',
                'Core\\Middleware\\AccessMiddleware'
            );
            $middleware = array_merge($globalMiddleware, $middleware);
        }

        if (_DEVMODE_) {
            foreach ($middleware as &$middlewareItem) {

                if (strpos($middlewareItem, '/')) {
                    $miArray = explode('/', $middlewareItem);
                    $miClass = array_pop($miArray);
                    $middlewareItem = implode("\\", array_merge($miArray, array('Middleware', $miClass)));
                }

            }
        }

        return $middleware;

    }

    private function _setConfigItems($config) {

        $ignore = array('services', 'database', 'middleware');
        $map = array('security');

        foreach ($config as $configKey => $configValue) {
            if (!in_array($configKey, $ignore) && is_array($configValue)) {
                if (in_array($configKey, $map)) {
                    $itemName = 'Core\\Application\\' . ucwords($configKey);
                    $item = dynamic_loader($itemName, $configValue);
                } else {
                    $item = new ConfigItem($configValue);
                }
                $this->config[$configKey] = $item;
            } elseif (!is_array($configValue) && property_exists($this, $configKey)) {
                $this->$configKey = $configValue;
            }
        }

    }

    private function _otfServiceList($activeOnEnvironment) {
        $services = array();

        foreach (array_keys($activeOnEnvironment) as $service) {
            $dir = 'src/' . $service;
            if (file_exists($dir) && is_dir($dir) && file_exists($dir . '/service.yaml')) {
                array_push($services, $service);
            }
        }

        return $services;
    }

    private function _releaseConfigImport($env, $tier) {
        $configFile = 'config/' . $env . '-' . $tier . '.ser';
        if (file_exists($configFile)){
            return unserialize(file_get_contents($configFile));
        } else {
            $this->_otfConfigImport($env, $tier);
        }
    }

    private function _otfConfigImport($env, $tier) {
        if (!file_exists('config/' . $env . '.yaml')) {
            $this->_handleError(new \Exception("No configuration for identified environment", 500));
        }

        $rootConfig = yaml_parse_file('config/' . $env . '.yaml');

        if (
            !is_array($rootConfig) ||
            !array_key_exists('tiers', $rootConfig) ||
            !is_array($rootConfig['tiers']) ||
            !array_key_exists($tier, $rootConfig['tiers'])
        ) {
            $this->_handleError(new \Exception("No configuration for identified tier", 500));
        }

        $tierConfig = $rootConfig['tiers'][$tier];
        unset($rootConfig['tiers']);

        $combinedConfig = $this->_deepMerge($rootConfig, $tierConfig);

        if (array_key_exists('mappings', $combinedConfig)) {
            unset($combinedConfig['mappings']);
        }

        return $combinedConfig;
    }

    private function _getEnvAndTier() {

        // First, try to infer this from a request header
        if (array_key_exists('HTTP_IS_IDENTITY', $_SERVER)) {
            $identity = explode("-", strtolower($_SERVER['HTTP_IS_IDENTITY']));
            $env = $identity[0];
            if (count($identity) > 1 && in_array($identity[1], array(TIER_LOCAL, TIER_PRERELEASE, TIER_RELEASE))){
                $tier = $identity[1];
            } else {
                $tier = TIER_RELEASE;
            }

            return array(
                'env' => $env,
                'tier' => $tier
            );
        }

        // No is-identity request header, so fall back to domain mapping
        $domain = strtolower($_SERVER['HTTP_HOST']);
        $domainElements = explode('.', $domain);
        if (count($domainElements) > 2 && substr($domain, 0, 3) === 'api') {
            array_shift($domainElements);
        }
        $domain = implode('.', $domainElements);
        if (_DEVMODE_) {
            $identity = $this->_otfDomainSearcher($domain);
        } else {
            $identity = $this->_releaseDomainSearcher($domain);
        }

        if ($identity === false) {
            $this->_handleError(new \Exception('Target environment could not be identified', '502'));
        } else {
            return $identity;
        }

    }

    private function _otfDomainSearcher($domain) {
        $configs = scandir('config');

        foreach ($configs as $config) {
            if (!is_dir($config) && substr($config, -4) === 'yaml' && $config !== 'global.yaml') {
                $configData = yaml_parse_file('config/' . $config);
                if (array_key_exists('tiers', $configData) && is_array($configData['tiers'])) {
                    foreach ($configData['tiers'] as $tier => $tierData) {
                        if (array_key_exists('mappings', $tierData) && is_array($tierData['mappings'])) {
                            if (in_array($domain, $tierData['mappings'])) {
                                return array(
                                    'env' => $configData['name'],
                                    'tier' => $tier
                                );
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    private function _releaseDomainSearcher($domain) {
        if (file_exists('config/domains.ser')) {

            $map = unserialize(file_get_contents('config/domains.ser'));
            if (array_key_exists($domain, $map)) {
                return $map[$domain];
            } else {
                return false;
            }

        } else {
            // Domain map doesn't exist, try using the on-the-fly searcher instead
            return $this->_otfDomainSearcher($domain);
        }
    }

    private function _deepMerge($array1, $array2) {
        if (!is_array($array1)) {
            $array1 = array($array1);
        }
        if (!is_array($array2)) {
            $array2 = array($array2);
        }

        foreach ($array2 as $arrKey => $arrVal) {
            if (!array_key_exists($arrKey, $array1)) {
                $array1[$arrKey] = $arrVal;
            } elseif (is_array($array1[$arrKey]) || is_array($arrVal)) {
                $array1[$arrKey] = $this->_deepMerge($array1[$arrKey], $arrVal);
            } else {
                $array1[$arrKey] = $arrVal;
            }
        }

        return $array1;
    }

    // TODO getUrl and remove Application dependencies from *
}

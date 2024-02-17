<?php

namespace Core\Routing;

class Route {

    private $url;
    private $methods = array();
    private $controller;

    function __construct($service, $url, $config) {
        $this->url = $url;
        $this->_generateController($service, $config['controller']);

        foreach($config['methods'] as $method => $methodConfig) {
            $comboConfig = array_merge($config, $methodConfig);
            $this->methods[$method] = new RouteMethod(
                $method,
                $methodConfig['arguments'],
                $config['returns'],
                array_key_exists('authentication', $comboConfig) ? $comboConfig['authentication']: false,
                array_key_exists('access-control', $comboConfig) ? $comboConfig['access-control']: false
            );
        }

    }

    public function handle($method, $args) {
        $details = array(
            'route' => $this->url,
            'controller' => $this->controller,
            'method' => $method,
        );
        if (array_key_exists($method, $this->methods)){
            $methodDetails = $this->methods[$method]->handle($args);
        } else {
            return false;
        }
        if ($method !== false && $methodDetails !== false){
            return array_merge($details, $methodDetails);
        } else {
            return false;
        }
    }

    private function _generateController($service, $controller) {
        $handlingController = explode('/', $controller);
        $handlingControllerLast = array_pop($handlingController);
        $handlingController[] = "Controllers";
        $handlingController[] = $handlingControllerLast;
        $this->controller = $service . "\\" . implode("\\", $handlingController);
    }

}

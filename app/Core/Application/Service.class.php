<?php

namespace Core\Application;

use Core\Routing\Route;

class Service {

    private $name;
    private $url;
    private $routes;

    private $_filter = null;

    function __construct($serviceName) {
        $this->name = $serviceName;
        $this->url = $this->_formatUrl($this->name);
        $this->_loadRoutes();
    }

    public function handle($method, $args) {

        if ($args[0] === $this->url) {
            array_shift($args);
        }

        if (array_key_exists($args[0], $this->routes)){
            $url = $args[0];
            array_shift($args);
            if ($this->routes[$url]->handle($method, $args) === false) {
                return false;
            } else {
                return array_merge(array('service' => $this->url), $this->routes[$url]->handle($method, $args));
            }
        } else {
            return false;
        }

    }

    private function _loadRoutes() {
        global $indigoStorm;

        if (is_null($this->routes)) {
            $this->routes = array();
        }

        $baseFile = 'src/' . $this->name . '/service.';
        if(file_exists($baseFile . 'ser')){
            $serviceDefinition = unserialize(file_get_contents($baseFile . 'ser'));
        }else{
            $serviceDefinition = yaml_parse_file($baseFile . 'yaml');
        }


        foreach ($serviceDefinition['routes'] as $routeUrl => $routeConfig) {

            if (array_key_exists('route-defaults', $serviceDefinition)) {
                $routeConfig = array_merge($serviceDefinition['route-defaults'], $routeConfig);
            }

            if (
                (
                    array_key_exists('interface-only', $routeConfig) &&
                    $routeConfig['interface-only'] &&
                    $indigoStorm->calledByInterface()
                ) ||
                (
                    (
                        !array_key_exists('interface-only', $routeConfig) ||
                        $routeConfig['interface-only'] !== true
                    ) &&
                    $this->_urlMatch($routeUrl)
                )
            ) {
                $this->routes[$routeUrl] = new Route($this->name, $routeUrl, $routeConfig);
            }

        }
    }

    private function _formatUrl($name) {
        if(strtoupper($name) == $name){
            return strtolower($name);
        }else{
            return strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', $name));
        }
    }

    private function _urlMatch($route) {
        if (is_null($this->_filter)) {
            $trimmedRequestUri = $_SERVER['REQUEST_URI'];
            preg_match_all('/([^\?]*)\??[^?]*/', $trimmedRequestUri, $removeQueryString);
            if(is_array($removeQueryString) && count($removeQueryString) == 2){
                if(is_array($removeQueryString[1]) && count($removeQueryString[1]) == 2){
                    $trimmedRequestUri = $removeQueryString[1][0];
                }
            }
            $inRoute = explode("/", $trimmedRequestUri);
            if ($inRoute[0] === '') {
                array_shift($inRoute);
            }
            if ($inRoute[0] === $this->url && count($inRoute) > 1) {
                $this->_filter = $inRoute[1];
            } else {
                $this->_filter = $inRoute[0];
            }
        }
        return strtolower($this->_filter) === strtolower($route);
    }

}

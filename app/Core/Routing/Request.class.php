<?php

namespace Core\Routing;

use Core\Models\RequestTree;

class Request {

    private $args;
    private $handler = array(
        'controller' => null,
        'function' => null,
    );
    private $routeName;
    private $service;

    private $tree = null;
    private $key = null;
    private $user = null;

    private $authentication = false;
    private $accessControl = false;

    public function requiresAuthentication() {
        return $this->authentication;
    }

    public function accessControlType() {
        return $this->accessControl;
    }

    public function getKey() {
        return $this->key;
    }

    public function setKey($key){
        if (is_null($this->key)){
            $this->key = $key;
        }
    }

    public function getUser() {
        return $this->user;
    }

    public function setUser($user){
        if (is_null($this->user)){
            $this->user = $user;
        }
    }

    public function getTree() {
        return $this->tree;
    }

    public function setTree(RequestTree $tree) {
        if (is_null($this->tree)){
            $this->tree = $tree;
        }
    }

    public function getParsedBody() {
        return json_decode(file_get_contents('php://input'), true);
    }

    public function getServerParam($param) {
        if (array_key_exists($param, $_SERVER)) {
            return $_SERVER[$param];
        } else {
            return null;
        }
    }

    public function getHeader($header) {
        $header = "HTTP_" . strtoupper($header);
        $header = preg_replace('/[^\da-z]/i', '_', $header);
        $value = $this->getServerParam($header);
        $explode = explode(',', $value);
        if ($explode === array('')) {
            return null;
        } else {
            return $explode;
        }
    }

    public function hasHeader($header) {
        return $this->getHeader($header) !== null;
    }

    public function getHeaders() {
        $headers = array();
        foreach($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $headers[substr($key, 5)] = explode(',' , $value);
            }
        }
        return $headers;
    }

    function __construct($handler) {
        $this->handler['function'] = 'handle' . ucwords($handler['method']);
        $this->handler['controller'] = dynamic_loader($handler['controller']);
        $this->routeName = $handler['service'] . '/' . $handler['route'];
        $this->service = $handler['service'];
        $this->args = $this->_formatArgs($handler['args']);
        $this->authentication = array_key_exists('authentication', $handler) && $handler['authentication'];
        $this->accessControl = array_key_exists('accessControl', $handler) && $handler['accessControl'];

    }

    public function getRouteHandler() {
        return $this->handler;
    }

    public function getMethod() {
        return strtolower($_SERVER['REQUEST_METHOD']);
    }

    public function isOptions() {
        return strtolower($_SERVER['REQUEST_METHOD']) === HTTP_METHOD_GET;
    }

    public function getArgs() {
        return $this->args;
    }

    private function _formatArgs($argNames) {
        if ($argNames === true) {
            return array();
        } else {
            if (is_string($argNames)) {
                $argNames = array($argNames);
            }
            $framgments = $this->_stripFirstFragments($argNames);
            $return = array();
            $i = 0;
            foreach($argNames as $argName) {
                $return[$argName] = $framgments[$i];
                $i++;
            }
            return $return;
        }
    }

    private function _stripFirstFragments($argNames, $fragments = null) {
        if (is_null($fragments)){
            $fragments = $this->_getUrlFragments();
        }

        if (count($argNames) < count($fragments)) {
            array_shift($fragments);
        }
        if (count($argNames) < count($fragments)) {
            $fragments = $this->_stripFirstFragments($argNames, $fragments);
        }

        return $fragments;

    }

    private function _getUrlFragments() {
        $requestUri = $_SERVER['REQUEST_URI'];
        preg_match_all('/([^\?]*)\??[^?]*/', $requestUri, $removeQueryString);
        if(is_array($removeQueryString) && count($removeQueryString) == 2){
            if(is_array($removeQueryString[1]) && count($removeQueryString[1]) == 2){
                $requestUri = $removeQueryString[1][0];
            }
        }
        $inRoute = explode("/", $requestUri);
        if ($inRoute[0] === '') {
            array_shift($inRoute);
        }
        return $inRoute;
    }

    public function getRouteName() {
        return $this->routeName;
    }

    public function getHandlingService() {
        return $this->service;
    }

}

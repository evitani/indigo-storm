<?php

namespace Core\Routing;

class Router {

    protected $request = null;
    protected $response = null;

    public function getRequest() {
        return $this->request;
    }

    public function handle($services, $middleware) {
        $method = strtolower($_SERVER['REQUEST_METHOD']);
        $args = $this->_getUrlFragments();
        $middlewareTree = $this->_buildMiddlewareTree($middleware);

        if ($method === HTTP_METHOD_OPTIONS) {
            $this->_handleOptions($services, $args);
        } else{
            foreach($services as $service){
                $handler = $service->handle($method, $args);
                if($handler !== false){
                    $this->follow($handler, $middlewareTree);
                    return true;
                }
            }
            throw new \Exception("No suitable route found", 404);
        }
    }

    private function _handleOptions($services, $args) {
        $options = array(
            HTTP_METHOD_GET => false,
            HTTP_METHOD_POST => false,
            HTTP_METHOD_PUT => false,
            HTTP_METHOD_DELETE => false
        );

        foreach ($services as $service) {
            foreach ($options as $option => $ignore) {
                $handler = $service->handle($option, $args);
                if ($handler !== false) {
                    $options[$option] = true;
                }
            }
        }

        $this->response = new Response();
        $allowedOptions = array();
        foreach ($options as $option => $include) {
            if ($include) {
                array_push($allowedOptions, strtoupper($option));
            }
        }
        $allowedHeaders = array(
            'Access-Control-Allow-Origin',
            'Pragma',
            'Cache-Control',
            'If-Modified-Since',
            'Content-Type',
            'enctype',
            'is-api-key',
            'is-session',
            'is-triggered-by',
            'is-identity',
        );

        foreach ($allowedHeaders as $allowedHeader) {
            $this->response->withHeader('Access-Control-Allow-Headers', $allowedHeader);
        }
        foreach ($options as $option => $include) {
            if ($include) {
                $this->response->withHeader('Access-Control-Allow-Methods', strtoupper($option));
            }
        }
        $this->response->withHeader('Access-Control-Allow-Origin', '*');
        $this->response->withJson($allowedOptions);
        $this->response->serve();
        exit();
    }

    private function follow($handler, $middlewareTree) {

        $this->request = new Request($handler);
        $this->response = new Response($handler);

        if (is_array($middlewareTree) && count($middlewareTree) > 0){
            $middleware = dynamic_loader($middlewareTree[0]);
            $middleware->load($middlewareTree);
            $this->response = $middleware->handleMiddleware($this->request, $this->response);
        } else {
            $route = dynamic_loader($this->request->getRouteHandler()['controller']);
            $run = $route->{$this->request->getRouteHandler()['function']}(
                $this->request,
                $this->response,
                $this->request->getArgs()
            );
            if (is_object($run) && get_class($run) === get_class(new Response())) {
                $this->response = $run;
            } else {
                $this->response = $this->response->withContent($run);
            }
        }

        $this->response->serve();

    }

    private function _buildMiddlewareTree($middleware) {
        $tree = array();
        foreach($middleware as $mw => $sortOrder) {
            array_push($tree, $mw);
        }
        return $tree;
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

}

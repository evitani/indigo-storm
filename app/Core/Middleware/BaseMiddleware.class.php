<?php

namespace Core\Middleware;

use Core\Routing\Request;
use Core\Routing\Response;

class BaseMiddleware{

    protected $tree = array();
    protected $indexInTree = null;

    public function load($tree, $index = 0) {
        $this->tree = $tree;
        $this->indexInTree = $index;
    }

    public function handleMiddleware(Request $request, Response $response) {
        $response = $this->next($request, $response);
        return $response;
    }

    protected function next(Request $request, Response $response) {

        if (count($this->tree) > $this->indexInTree + 1) {
            $next = dynamic_loader($this->tree[$this->indexInTree + 1]);
            $next->load($this->tree, $this->indexInTree + 1);
            return $next->handleMiddleware($request, $response);
        } else {
            $route = dynamic_loader($request->getRouteHandler()['controller']);
            $run = $route->{$request->getRouteHandler()['function']}($request, $response, $request->getArgs());
            if (is_object($run) && get_class($run) === get_class(new Response())) {
                return $run;
            } else {
                $response = $response->withContent($run);
                return $response;
            }
        }

    }

}

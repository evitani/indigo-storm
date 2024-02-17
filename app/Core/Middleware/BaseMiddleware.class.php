<?php

namespace Core\Middleware;

class BaseMiddleware{

    public function __invoke($request, $response, $next){
        $response = $next($request, $response);

        return $response;
    }

}

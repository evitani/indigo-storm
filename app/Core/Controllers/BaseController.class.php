<?php

namespace Core\Controllers;


/**
 * Basic Controller template. This defines the possible options for a controller, so if an extension does not include it
 * the exception method in this controller will catch any attempts to use it.
 *
 * @package Core\Controllers
 */
class BaseController{

    public function handleGet($request, $response, $args){
        throw new \Exception('Could not handle request using this method (GET)', 405);
    }

    public function handlePost($request, $response, $args){
        throw new \Exception('Could not handle request using this method (POST)', 405);
    }

    public function handlePut($request, $response, $args){
        throw new \Exception('Could not handle request using this method (PUT)', 405);
    }

    public function handleDelete($request, $response, $args){
        throw new \Exception('Could not handle request using this method (DELETE)', 405);
    }

    public function __invoke($request, $response, $args){
        throw new \Exception('Could not handle request using this method (Invoke)', 405);
    }

}

<?php

namespace Core\Middleware;

use Core\Models\RequestTree;

class ContinuityMiddleware extends BaseMiddleware{

    protected function checkFailReason($path, $services){

        $exploded = explode('/', $path);

        $formattedService = str_replace(" ", "", ucwords(str_replace("-", " ", $exploded[1])));

        if(!array_key_exists($formattedService, $services)){
            throw new \Exception("Service not found", 404);
        }

        $service = $services[$formattedService];

        if(!array_key_exists($exploded[2], $service)){
            throw new \Exception("Route not found", 404);
        }

        return;

    }

    public function __invoke($request, $response, $next){
        global $Application;

        if($request->isOptions()){

            $response = $next($request, $response);

            return $response;

        }else{

            if($_SERVER['REQUEST_URI'] !== '/favicon.ico'){

                if($request->hasHeader('is-request-tree')){
                    //This request is part of an ongoing tree
                    islog(LOG_INFO, 'Ongoing tree found:' . $request->getHeader('is-request-tree')[0]);
                    $Application->setTree(new RequestTree($request->getHeader('is-request-tree')[0]));
                    $Application->calledByInterface = true;
                    $firstInTree = false;
                    if($Application->tree->getMetadata('user')){
                        $Application->setUser($Application->tree->getMetadata('user'));
                    }
                }else{
                    //This request is the first in a tree, generate a trace
                    $tree = new RequestTree();
                    $tree->startTree($_SERVER['REQUEST_URI']);
                    $Application->setTree($tree);
                    $firstInTree = true;
                }

                if(is_null($request->getAttribute('route'))){
                    $this->checkFailReason($request->getURI()->getPath(), $Application->getServices());
                    throw new \Exception("Unsupported method or arguments", 500);
                }else{
                    $routeName = $request->getAttribute('route')->getName();
                }

                $Application->tree->getAll();
                $Application->tree->setInteraction(strval(microtime(true)), $routeName);
                $Application->tree->setCurrentInteraction($routeName);
                $Application->tree->persist();

            }

            $response = $next($request, $response);

            if($firstInTree){
                $Application->setTree(new RequestTree($Application->tree->getName()));
                $Application->tree->getAll();

                if(!is_null($Application->key)){
                    $Application->tree->setMetadata('apiKey', $Application->key->getId());
                }

                $Application->tree->endTree();
            }

            return $response;

        }
    }

}

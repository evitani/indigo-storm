<?php

namespace Core\Middleware;

use Core\Models\RequestTree;

class ContinuityMiddleware extends BaseMiddleware{

    public function __invoke($request, $response, $next){
        global $Application;

        if($request->isOptions()){

            $response = $next($request, $response);

            return $response;

        }else{

            if($_SERVER['REQUEST_URI'] !== '/favicon.ico'){

                if($request->hasHeader('is-request-tree')){
                    //This request is part of an ongoing tree
                    syslog(LOG_INFO, 'Ongoing tree found:' . $request->getHeader('is-request-tree')[0]);
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

                $Application->tree->getAll();
                $Application->tree->setInteraction(strval(microtime(true)), $request->getAttribute('route')->getName());
                $Application->tree->setCurrentInteraction($request->getAttribute('route')->getName());
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

<?php

namespace Core\Middleware;

use Core\Models\RequestTree;
use Core\Routing\Request;
use Core\Routing\Response;

class ContinuityMiddleware extends BaseMiddleware{

    public function handleMiddleware(Request $request, Response $response){


        if($request->isOptions()){

            $response = $this->next($request, $response);

            return $response;

        }else{

            if($request->hasHeader('is-request-tree')){
                //This request is part of an ongoing tree
                islog(LOG_INFO, 'Ongoing tree found:' . $request->getHeader('is-request-tree')[0]);
                $request->setTree(new RequestTree($request->getHeader('is-request-tree')[0]));
                $firstInTree = false;
                if($request->getTree()->getMetadata('user')){
                    $request->setUser($request->getTree()->getMetadata('user'));
                }
            }else{
                //This request is the first in a tree, generate a trace
                $tree = new RequestTree();

                // This request was triggered as a task, so log that fact
                if ($request->hasHeader('is-triggered-by')) {
                    $tree->setMetadata('triggeredBy', $request->getHeader('is-triggered-by')[0]);
                }

                $tree->startTree($_SERVER['REQUEST_URI']);
                $request->setTree($tree);
                $firstInTree = true;
            }

            $routeName = $request->getRouteName();

            $request->getTree()->getAll();
            $request->getTree()->setInteraction(strval(microtime(true)), $routeName);
            $request->getTree()->setCurrentInteraction($routeName);
            $request->getTree()->persist();

            $response = $this->next($request, $response);

            if($firstInTree){
                $request->setTree(new RequestTree($request->getTree()->getName()));
                $request->getTree()->getAll();
                $request->getTree()->endTree();
            }

            return $response;

        }

    }

}

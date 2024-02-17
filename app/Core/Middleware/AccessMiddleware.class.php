<?php

namespace Core\Middleware;

use Core\Models\ApiUser;

class AccessMiddleware extends BaseMiddleware{

    public function __invoke($request, $response, $next){
        global $Application;

        if($request->isOptions() || $request->getAttribute('route')->getName() == 'user/api-user'){

            $response = $next($request, $response);
            return $response;

        }

        // Get the session
        $headerSession = $request->getHeader('is-session');

        $sessionName = false;
        if(is_array($headerSession) && isset($headerSession[0])){
            //A session key is present in the header
            $sessionName = $headerSession[0];
        }elseif(array_key_exists('session', $_GET) && $_GET['session'] !== ''){
            //A session key is present in the query string
            $sessionName = $_GET['session'];
        }

        if(isset($sessionName) && $sessionName !== false){
            $session = new ApiUser($sessionName);
        }else{
            $session = false;
        }

        $sessionExists = $session !== false && !is_null($session);

        // Run this only if the endpoint has access control AND this is the first in a tree
        $firstInTree = is_null($Application->tree) || count($Application->tree->getInteraction()) === 1;
        $accessType = $Application->isEndpointAccessControlled($request->getAttribute('route')->getName(), $_SERVER['REQUEST_METHOD']);

        if($firstInTree && $accessType !== false){

            // Endpoint requires access controls but there isn't a session
            if(!$sessionExists){
                throw new \Exception("Unauthorised", 401);
            }else{
                $Application->tree->setMetadata('user', $session->getUser());
                $Application->setUser($session->getUser());
            }

            if(is_string($accessType)){
                $accessType = array($accessType);
            }

            // Now handle the access control options
            if(is_array($accessType)){

                foreach($accessType as $group){
                    if($session->checkAccess($group)){
                        $response = $next($request, $response);
                        return $response;
                        break;
                    }
                }

                throw new \Exception("Unauthorised", 401);

            }elseif($accessType === true){

                $response = $next($request, $response);
                return $response;

            }else{
                throw new \Exception("Access control malformed", 500);
            }

        }else{
            $response = $next($request, $response);
            return $response;
        }

    }
}


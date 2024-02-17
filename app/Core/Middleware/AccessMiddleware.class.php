<?php

namespace Core\Middleware;

use Core\Models\ApiUser;
use Core\Routing\Request;
use Core\Routing\Response;

class AccessMiddleware extends BaseMiddleware{

    public function handleMiddleware(Request $request, Response $response){

        if($request->isOptions() || $request->getRouteName() === 'user/api-user'){

            $response = $this->next($request, $response);
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
        $firstInTree = is_null($request->getTree()) || count($request->getTree()->getInteraction()) === 1;
        $accessType = $request->accessControlType();

        if($firstInTree && $accessType !== false){

            // Endpoint requires access controls but there isn't a session
            if(!$sessionExists){
                throw new \Exception("Unauthorised", 401);
            }else{
                $request->getTree()->setMetadata('user', $session->getUser());
                $request->setUser($session->getUser());
            }

            if(is_string($accessType)){
                $accessType = array($accessType);
            }

            // Now handle the access control options
            if(is_array($accessType)){

                foreach($accessType as $group){
                    if($session->checkAccess($group)){
                        $response = $this->next($request, $response);
                        return $response;
                        break;
                    }
                }

                throw new \Exception("Unauthorised", 401);

            }elseif($accessType === true){

                $response = $this->next($request, $response);
                return $response;

            }else{
                throw new \Exception("Access control malformed", 500);
            }

        }else{
            $response = $this->next($request, $response);
            return $response;
        }

    }

}


<?php

namespace Core\Middleware;

use Core\Models\ApiKey;

class KeyMiddleware extends BaseMiddleware{

    public function __invoke($request, $response, $next){
        global $Application;

        if($request->isOptions()){

            $response = $next($request, $response);

            return $response;

        }else{

            $requiresAuthentication = $Application->isEndpointSecure($request->getAttribute('route')->getName(), $_SERVER['REQUEST_METHOD']);

            if($requiresAuthentication && count($Application->tree->getInteraction()) === 1){
                //First item in tree on an authenticated call, so check for an API key

                $headerKey = $request->getHeader('is-api-key');

                $keyName = false;
                if(is_array($headerKey) && isset($headerKey[0])){
                    //An API key is present in the header
                    $keyName = $headerKey[0];
                }elseif(array_key_exists('apiKey', $_GET) && $_GET['apiKey'] !== ''){
                    //An API key is present in the query string
                    $keyName = $_GET['apiKey'];
                }

                if($keyName !== false){
                    //Process the API key
                    try{
                        $key = new ApiKey($keyName);
                    }catch (\Exception $e){
                        //Invalid key used, act as no key
                        $key = false;
                    }
                }else{
                    //No API key to process
                    $key = false;
                }

                if($key === false){

                    throw new \Exception('Authentication required', 401);

                }else{

                    $directLicense = $key->getLicense($request->getAttribute('route')->getName());

                    $authenticated = false;

                    if(is_array($directLicense)){
                        $directLicense = array_flip($directLicense);
                        if(array_key_exists(strtolower($_SERVER['REQUEST_METHOD']), $directLicense)){
                            $authenticated = true;
                        }
                    }

                    if($authenticated === false){
                        $indirectArray = explode('/', $request->getAttribute('route')->getName());
                        $indirectName = $indirectArray[0];
                        $indirectLicense = $key->getLicense($indirectName . '/*');

                        if(is_array($indirectLicense)){
                            $indirectLicense = array_flip($indirectLicense);
                            if(array_key_exists(strtolower($_SERVER['REQUEST_METHOD']), $indirectLicense)){
                                $authenticated = true;
                            }
                        }
                    }

                    if($authenticated){
                        $Application->setKey($key);
                        $response = $next($request, $response);

                        return $response;
                    }else{
                        throw new \Exception('Authentication required', 401);
                    }

                }

            }else{
                $response = $next($request, $response);

                return $response;
            }

        }

    }
}

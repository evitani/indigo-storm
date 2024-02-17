<?php

namespace Core\Middleware;

use Core\Models\ApiKey;
use Core\Routing\Request;
use Core\Routing\Response;

class KeyMiddleware extends BaseMiddleware{

    public function handleMiddleware(Request $request, Response $response){

        if($request->isOptions()){

            $response = $this->next($request, $response);

            return $response;

        }else{

            $requiresAuthentication = $request->requiresAuthentication();

            if($requiresAuthentication && count($request->getTree()->getInteraction()) === 1){
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

                    $directLicense = $key->getLicense($request->getRouteName());

                    $authenticated = false;

                    if(is_array($directLicense)){
                        $directLicense = array_flip($directLicense);
                        if(array_key_exists($request->getMethod(), $directLicense)){
                            $authenticated = true;
                        }
                    }

                    if($authenticated === false){
                        $indirectArray = explode('/', $request->getRouteName());
                        $indirectName = $indirectArray[0];
                        $indirectLicense = $key->getLicense($indirectName . '/*');

                        if(is_array($indirectLicense)){
                            $indirectLicense = array_flip($indirectLicense);
                            if(array_key_exists($request->getMethod(), $indirectLicense)){
                                $authenticated = true;
                            }
                        }
                    }

                    if($authenticated){
                        $request->setKey($key);
                        $request->getTree()->setMetadata('apiKey', $key->getId());
                        $response = $this->next($request, $response);

                        return $response;
                    }else{
                        throw new \Exception('Authentication required', 401);
                    }

                }

            }else{
                $response = $this->next($request, $response);

                return $response;
            }

        }

    }

}

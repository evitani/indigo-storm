<?php

$trimmedRequestUri = $_SERVER['REQUEST_URI'];
preg_match_all('/([^\?]*)\??[^?]*/', $trimmedRequestUri, $removeQueryString);
if(is_array($removeQueryString) && count($removeQueryString) == 2){
    if(is_array($removeQueryString[1]) && count($removeQueryString[1]) == 2){
        $trimmedRequestUri = $removeQueryString[1][0];
    }
}

$inRoute = explode("/", $trimmedRequestUri);
if($inRoute[0] === ''){
    array_shift($inRoute);
}

$runningServices = explode(',', _RUNNINGSERVICE_);

$registeredRoute = false;

$optionRoutes = array();

foreach($runningServices as $runningService){

    if(strtoupper($runningService) == $runningService){
        $runningServiceWorker = strtolower($runningService);
    }else{
        $runningServiceWorker = $runningService;
    }
    $runningServiceId = strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', $runningServiceWorker));
    $urlTypes = array('', $runningServiceId . '/');

    if($inRoute[0] === $runningServiceId && count($inRoute) > 1){
        $routeName = $inRoute[1];
    }else{
        $routeName = $inRoute[0];
    }

    $inThisService = $Application->getServices($runningService);

    include 'src/' . $runningService . '/Service.definition.php';

    foreach($serviceDefinition as $sdService => $sdRoutes){
        if(!array_key_exists($sdService, $inThisService)){
            if(array_key_exists('interface-only', $sdRoutes) && $sdRoutes['interface-only']){
                $inThisService[$sdService] = 'interface-only';
            }
        }
    }

    if(array_key_exists($routeName, $inThisService)){
        //Found a route that matches in name that is served by this services. Now load the service definition.

        foreach($serviceDefinition[$routeName]['methods'] as $methodName => $methodParameters){

            if(strtolower($_SERVER['REQUEST_METHOD']) === $methodName || $_SERVER['REQUEST_METHOD'] === 'OPTIONS'){

                //The route in the service supports the method used.

                if(array_key_exists('authentication', $serviceDefinition[$routeName])){
                    if(array_key_exists($methodName, $serviceDefinition[$routeName]['authentication']) && $serviceDefinition[$routeName]['authentication'][$methodName] === true){
                        $Application->enableEndpointSecurity($runningServiceId . "/" . $routeName, $methodName);
                    }
                }

                foreach($methodParameters as $numberOfParamters => $parameterDefinitions){

                    //We have a route that matches, generate and attach its variants to the app.
                    switch ($numberOfParamters){
                        case 0:
                            $endpoint = $routeName;
                            break;
                        case 1:
                            $endpoint = $routeName . '/{' . $parameterDefinitions . '}';
                            break;
                        default:
                            $endpoint = $routeName . '/{' . implode('}/{', $parameterDefinitions) . '}';
                            break;
                    }

                    $handlingController = explode('/', $serviceDefinition[$routeName]['controller']);
                    $handlingControllerLast = array_pop($handlingController);
                    $handlingController[] = "Controllers";
                    $handlingController[] = $handlingControllerLast;


                    $handlingController = $runningService . "\\" . implode("\\", $handlingController);
                    $handlingMethod = 'handle' . ucwords($methodName);

                    if(array_key_exists('returns', $serviceDefinition[$routeName])){
                        $returnType = strtolower($serviceDefinition[$routeName]['returns']);
                    }else{
                        $returnType = 'json';
                    }

                    foreach($urlTypes as $urlType){

                        $indigoStorm->$methodName("/" . $urlType . $endpoint, function($request, $response, $args){
                            global $handlingController;
                            global $handlingMethod;
                            global $returnType;
                            global $inThisService;
                            global $routeName;
                            global $Application;

                            if($inThisService[$routeName] === 'interface-only' && !$Application->calledByInterface){
                                throw new \Exception('Interface route called by non-interface.', 401);
                            }

                            $controller = dynamic_loader($handlingController);

                            $response = $response->withHeader('Access-Control-Allow-Headers', '*');

                            switch ($returnType){
                                case 'file':
                                    $file = $controller->$handlingMethod($request, $response, $args);
                                    $response->write($file['content']);
                                    $response = $response->withHeader('Content-Type', $file['mime']);
                                    break;
                                case 'json':
                                default:
                                    $response = $response->withJson($controller->$handlingMethod($request, $response, $args));
                                    break;
                            }

                            return $response;

                        })->setName($runningServiceId . "/" . $routeName);

                        if(!in_array($urlType . $endpoint, $optionRoutes)){
                            $indigoStorm->options("/" . $urlType . $endpoint, function($request, $response){
                                return $response;
                            })->setName($runningServiceId . "/" . $routeName . ":OPTIONS");
                            array_push($optionRoutes, $urlType . $endpoint);
                        }

                    }

                }

            }

        }

    }

}

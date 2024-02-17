<?php

//First filter out any favicon requests.
if($_SERVER['REQUEST_URI'] === '/favicon.ico'){
    http_response_code(404);
    exit;
}

define('_RUNNINGDIR_', getcwd());

//Define the global constants needed by the application.
require_once 'app/definitions.inc.php';

//Load the service config file to define what service/s are running in this instance (comma-separated).
require_once 'config/service.config.php';

//Include the autoloaders (vendor loads Slim and other Composer packages, app loads IS classes).
require 'vendor/autoload.php';
require_once 'app/autoload.inc.php';

//Define the Application object.
$Application = new Core\Models\Application;

//Detect the target environment for the request and add Application->environment.
require_once 'app/configure.inc.php';

//Include any service-specific requirements
require_once 'app/autoinclude.inc.php';

$slimContainer = new \Slim\Container();

//Build an error handler for Slim to use.
$slimContainer['errorHandler'] = function($slimContainer){
    return function($request, $response, $exception) use ($slimContainer){

        if($exception->getCode() > 0){
            $errno = $exception->getCode();
        }else{
            $errno = 500;
        }

        try{

            global $Application;

            if(!is_null($Application->tree)){
                $currentInteraction = $Application->tree->getCurrentInteraction();
                $Application->setTree(new \Core\Models\RequestTree($Application->tree->getName()));
                $Application->tree->setCurrentInteraction($currentInteraction);
                $Application->tree->logException($errno, $exception->getMessage());
            }

        }catch (Exception $e){
            return $slimContainer['response']->withStatus($errno)
                                             ->withHeader('Content-Type', 'text/html')
                                             ->write($exception->getMessage());
        }

        return $slimContainer['response']->withStatus($errno)
                                         ->withHeader('Content-Type', 'text/html')
                                         ->write($exception->getMessage());
    };
};

//Set routing before instantiating middleware (this allows faster loading and better security).
$slimContainer['settings']['determineRouteBeforeAppMiddleware'] = true;

//Register a configured copy of the app, ready to add middleware and routes.
$indigoStorm = new \Slim\App($slimContainer);

//Load all required Middleware.
foreach($Application->getMiddleware() as $mw){
    $indigoStorm->add(dynamic_loader($mw));
}

//To minimise on application overhead, dynamically register only the routes that may be required to fulfil this request.
require_once 'app/routing.inc.php';

//Before we run the app, check to see if it is the latest version, and warn if it isn't
if(floatval(IS_VERSION) < floatval(IS_MOSTRECENT)){
    syslog(
        LOG_WARNING,
        "Version " . IS_VERSION . " of Indigo Storm is out of date, update to " . IS_MOSTRECENT
    );
}

//Run the combined application.
$indigoStorm->run();

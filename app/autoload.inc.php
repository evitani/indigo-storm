<?php

//Register an autoloader to handle use of Indigo Storm classes.
spl_autoload_register(function($class_name){
    $matches = explode("\\", $class_name);

    //All IS classes need to have at least 3 components (the service namespace, class type, and name).
    if(count($matches) >= 3){

        //Extract the service namespace, direct Core/ and Service/ requests to the correct directories, or check service
        //is loaded in this instance if it's a direct service request.
        $serviceNamespace = $matches[0];

        if($serviceNamespace === 'Core'){
            //Core namespace classes come from the app.
            $rootLocation = 'app/Core';
        }elseif($serviceNamespace === 'Services'){
            //Service namespace classes (interfaces) are extracted from their service and registered separately.
            $rootLocation = 'services';

            if(_DEVMODE_){
                $rootLocation = 'src/';
            }else{
                $rootLocation = 'services';
            }

        }elseif(file_exists('src/' . $serviceNamespace)){
            //Service-specific classes are loaded from their service src.
            $rootLocation = 'src/' . $serviceNamespace;
        }

        if(isset($rootLocation) && $rootLocation !== ''){

            if($serviceNamespace === 'Services' && _DEVMODE_){
                $shiftMatches = $matches;
                array_shift($shiftMatches);
                $lastItem = array_pop($shiftMatches);
                $shiftMatches[] = 'Interfaces';
                $shiftMatches[] = $lastItem;
                $includeFragment = implode('/', $shiftMatches);
            }else{
                //Create the exact location of the class in the file structure.
                $shiftMatches = $matches;
                array_shift($shiftMatches);
                $includeFragment = implode('/', $shiftMatches);
            }

            if(stream_resolve_include_path($rootLocation . "/" . $includeFragment . ".class.php") !== false){
                //The class file exists at the expected location, so load it (only if it's loaded once)
                require_once $rootLocation . "/" . $includeFragment . ".class.php";
            }else{
                try{
                    //If the class hasn't loaded through IS, check it exists and auto-loads elsewhere.
                    class_exists($class_name, true);
                }catch (exception $e){
                    //Not doing anything with the catch right now.
                }
            }
        }

    }else{
        try{
            //If the class isn't loaded through IS, check it exists and auto-loads elsewhere.
            class_exists($class_name, true);
        }catch (exception $e){
            //Not doing anything with the catch right now.
        }
    }

});


//Define a dynamic class loader for use during route definition.
function dynamic_loader($class){
    return new $class;
}

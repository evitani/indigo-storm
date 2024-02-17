<?php

$services = $Application->getServices();

foreach($services as $service => $sc){
    if(stream_resolve_include_path('src/' . $service . '/Includes.definition.php') !== false){
        require 'src/' . $service . '/Includes.definition.php';

        foreach($includeDefinition as $definition => $includeType){
            switch ($includeType){
                case 'include':
                    include $definition;
                    break;
                case 'require':
                    require $definition;
                    break;
                case 'include_once':
                    include_once $definition;
                    break;
                case 'require_once':
                    require_once $definition;
                    break;
                default:
                    include $definition;
                    break;
            }
        }
    }
}

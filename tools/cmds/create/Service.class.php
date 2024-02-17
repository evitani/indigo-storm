<?php

namespace Tools\Create;

use Tools\Tool;

class Service extends Tool {

    public function run(){

        $serviceName = $this->formatServiceName($this->args['argument1']);

        $baseName = 'src/' . $serviceName;

        if (
            file_exists($baseName) &&
            is_dir($baseName) &&
            (
                file_exists($baseName . '/service.yaml') ||
                file_exists($baseName . '/Service.definition.php')
            )
        ) {
            $this->printLine('Service already exists', 0, true);
            exit;
        }

        mkdir($baseName);

        $subDirs = array('Controllers','Helpers','Interfaces','Middleware','Models');

        foreach ($subDirs as $subDir) {
            mkdir($baseName . '/' . $subDir);
        }

        $serviceConfig = array(
            'route-defaults' => array(
                'returns' => RETURN_JSON,
                'interface-only' => false,
                'authentication' => false,
                'access-control' => false,
            ),
            'routes' => array(),
        );

        yaml_emit_file($baseName . '/service.yaml', $serviceConfig);
        $this->printLine("Service created, attach to an environment to use", 0, true);

    }

    protected function formatServiceName($input) {

        $input = str_replace("-", " ", $input);
        if (strtoupper($input) !== $input){
            $input = ucwords($input);
        }
        $input = str_replace(" ", "", $input);

        return $input;
    }

}

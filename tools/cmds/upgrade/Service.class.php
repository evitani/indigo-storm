<?php

namespace Tools\Upgrade;

use Tools\Tool;

class Service extends Tool {

    public function run(){

        $workingDir = 'src/' . $this->args['target'];

        if (!file_exists($workingDir) || !is_dir($workingDir)) {
            $this->printLine('Service not found, try again.', 0, true);
            exit;
        } elseif (file_exists($workingDir . '/service.yaml')) {
            $this->printLine('This service has already been upgraded!', 0, true);
            exit;
        } elseif (!file_exists($workingDir . '/Service.definition.php')) {
            $this->printLine('Service missing definition, couldn\'t upgrade.', 0, true);
            exit;
        }

        $serviceDefinition = array();
        $this->printLine('Reading old service definition');
        require_once $workingDir . '/Service.definition.php';

        $newConfig = array(
            'route-defaults' => array(
                'returns' => RETURN_JSON,
                'interface-only' => false,
                'authentication' => false,
                'access-control' => false,
            ),
            'routes' => array(),
        );

        foreach ($serviceDefinition as $route => $routeConfig) {
            $newRoute = array(
                'controller' => $routeConfig['controller'],
                'methods' => array()
            );

            $globalReturn = false;
            $globalAuth = false;
            $globalAccess = false;
            $globalInterface = false;
            if (
                !is_array($routeConfig['returns']) &&
                !in_array($routeConfig['returns'], array(RETURN_JSON, null))
            ) {
                $newRoute['returns'] = $routeConfig['returns'];
                $globalReturn = true;
            }

            if (
                !is_array($routeConfig['interface-only']) &&
                !in_array($routeConfig['interface-only'], array(false, null))
            ) {
                $newRoute['interface-only'] = $routeConfig['interface-only'];
                $globalInterface = true;
            }

            if (
                !is_array($routeConfig['authentication']) &&
                !in_array($routeConfig['authentication'], array(false, null))
            ) {
                $newRoute['authentication'] = $routeConfig['authentication'];
                $globalAuth = true;
            }

            if (
                !is_array($routeConfig['access-control']) &&
                !in_array($routeConfig['access-control'], array(false, null))
            ) {
                $newRoute['access-control'] = $routeConfig['access-control'];
                $globalAccess = true;
            }

            foreach ($routeConfig['methods'] as $method => $methodConfig) {
                $method = strtolower($method);
                if (!in_array($method, array(HTTP_METHOD_GET, HTTP_METHOD_POST, HTTP_METHOD_PUT, HTTP_METHOD_PUT))) {
                    continue;
                }

                $newRoute['methods'][$method] = array(
                    'arguments' => array()
                );

                if (
                    !$globalReturn &&
                    is_array($routeConfig['returns']) &&
                    array_key_exists($method, $routeConfig['returns']) &&
                    !in_array($routeConfig['returns'][$method], array(RETURN_JSON, null))
                ) {
                    $newRoute['methods'][$method]['returns'] = $routeConfig['returns'][$method];
                }

                if (
                    !$globalInterface &&
                    is_array($routeConfig['interface-only']) &&
                    array_key_exists($method, $routeConfig['interface-only']) &&
                    !in_array($routeConfig['interface-only'][$method], array(false, null))
                ) {
                    $newRoute['methods'][$method]['interface-only'] = $routeConfig['interface-only'][$method];
                }

                if (
                    !$globalAuth &&
                    is_array($routeConfig['authentication']) &&
                    array_key_exists($method, $routeConfig['authentication']) &&
                    !in_array($routeConfig['authentication'][$method], array(false, null))
                ) {
                    $newRoute['methods'][$method]['authentication'] = $routeConfig['authentication'][$method];
                }

                if (
                    !$globalAccess &&
                    is_array($routeConfig['access-control']) &&
                    array_key_exists($method, $routeConfig['access-control']) &&
                    !in_array($routeConfig['access-control'][$method], array(false, null))
                ) {
                    $newRoute['methods'][$method]['access-control'] = $routeConfig['access-control'][$method];
                }

                foreach ($methodConfig as $args => $argList) {

                    $args = intval($args);

                    if ($args === 0) {
                        $newRoute['methods'][$method]['arguments'][0] = null;
                    } elseif ($args === 1 && is_array($argList)) {
                        $newRoute['methods'][$method]['arguments'][1] = $argList[0];
                    } else {
                        $newRoute['methods'][$method]['arguments'][$args] = $argList;
                    }

                }

            }

            $newConfig['routes'][$route] = $newRoute;
        }

        $this->printLine('Saving new service.yaml');
        yaml_emit_file($workingDir . '/service.yaml', $newConfig);

        $this->printLine('Cleaning up old files');
        unlink($workingDir . '/Service.definition.php');
        if (file_exists($workingDir . '/Includes.definition.php')){
            unlink($workingDir . '/Includes.definition.php');
        }
        if (file_exists($workingDir . '/Container.definition.php')){
            unlink($workingDir . '/Container.definition.php');
        }

        $this->printLine('Adding missing directories');
        if (!file_exists($workingDir . '/Controllers')) {
            mkdir($workingDir . '/Controllers');
        }
        if (!file_exists($workingDir . '/Helpers')) {
            mkdir($workingDir . '/Helpers');
        }
        if (!file_exists($workingDir . '/Interfaces')) {
            mkdir($workingDir . '/Interfaces');
        }
        if (!file_exists($workingDir . '/Middleware')) {
            mkdir($workingDir . '/Middleware');
        }
        if (!file_exists($workingDir . '/Models')) {
            mkdir($workingDir . '/Models');
        }

        $this->printLine("Service upgraded!", 0 ,true);

    }

}

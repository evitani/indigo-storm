<?php

namespace Tools\Attach;

use Tools\Tool;

class Route extends Tool{

    public function run(){

        $env = $this->args['environment'];
        if (strpos($env, '-') !== false) {
            $env = explode('-', $env);
            $tier = $this->formatArgument($env[1]);
            $env = $this->formatArgument($env[0]);
        } else {
            $tier = null;
            $env = $this->formatArgument($env);
        }

        $fileName = 'config/' . $env . '.yaml';

        if (!file_exists($fileName)) {
            $this->printLine("Environment not found", 0, true);
            exit;
        }

        $envConfig = yaml_parse_file($fileName);

        if (!is_null($tier) && !array_key_exists($tier, $envConfig['tiers'])) {
            $this->printLine("Tier not found", 0, true);
            exit;
        }

        $details = explode("/", $this->args['resource']);
        $service = $this->formatServiceName($details[0]);

        if (count($details) !== 2) {
            $this->printLine("Invalid route request", 0, true);
            exit;
        } elseif ($details[1] === '%' && array_key_exists('snapshot', $this->flags)) {
            $routes = $this->getSnapshot($service);
        } elseif ($details[1] === '%') {
            $routes = "%";
        } else {
            $routes = array(
                $details[1] => null
            );
        }

        if (is_null($tier)) {
            if (!array_key_exists('services', $envConfig)) {
                $envConfig['services'] = array();
            }
            if (!array_key_exists($service, $envConfig['services'])  || is_null($envConfig['services'][$service])) {
                $envConfig['services'][$service] = $routes;
            } elseif (is_array($envConfig['services'][$service]) && $routes !== '%') {
                $envConfig['services'][$service] = array_merge($envConfig['services'][$service], $routes);
            } elseif (is_array($envConfig['services'][$service]) && $routes === '%') {
                $envConfig['services'][$service] = '%';
            }
        } else {

            if (!array_key_exists('services', $envConfig['tiers'][$tier])) {
                $envConfig['tiers'][$tier]['services'] = array();
            }

            if (
                !array_key_exists($service, $envConfig['tiers'][$tier]['services']) ||
                is_null($envConfig['tiers'][$tier]['services'][$service])
            ) {
                $envConfig['tiers'][$tier]['services'][$service] = $routes;
            } elseif (is_array($envConfig['tiers'][$tier]['services'][$service]) && $routes !== '%') {
                $envConfig['tiers'][$tier]['services'][$service] = array_merge(
                    $envConfig['tiers'][$tier]['services'][$service],
                    $routes);
            } elseif (is_array($envConfig['tiers'][$tier]['services'][$service]) && $routes === '%'){
                $envConfig['tiers'][$tier]['services'][$service] = '%';
            }

        }

        yaml_emit_file($fileName, $envConfig);

        $this->printLine("Route added to environment", 0, true);

    }

    protected function getSnapshot($service) {
        $serviceConfig = yaml_parse_file('src/' . $service . '/service.yaml');
        if (array_key_exists('routes', $serviceConfig)) {
            $response = array();
            foreach($serviceConfig['routes'] as $route => $details) {
                $response[$route] = null;
            }
            return $response;
        } else {
            return array();
        }
    }

    protected function formatArgument($name) {
        return preg_replace('/[^a-z]/','', strtolower($name));
    }

    protected function formatServiceName($serviceName) {
        if (substr($serviceName, 0, 1) === strtolower(substr($serviceName, 0, 1))) {
            return str_replace(" ", "", ucwords(str_replace("-", " ", $serviceName)));
        } else {
            return $serviceName;
        }
    }

}

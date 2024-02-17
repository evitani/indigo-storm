<?php

namespace Tools\Attach;

use Tools\Tool;

class Service extends Tool{

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

        if (is_null($tier)) {
            if (!array_key_exists('services', $envConfig)) {
                $envConfig['services'] = array();
            }
            $service = $this->formatServiceName($this->args['resource']);
            if (!array_key_exists($service, $envConfig['services'])) {
                $envConfig['services'][$service] = null;
            }
        } else {

            if (!array_key_exists('services', $envConfig['tiers'][$tier])) {
                $envConfig['tiers'][$tier]['services'] = array();
            }
            $service = $this->formatServiceName($this->args['resource']);
            if (!array_key_exists($service, $envConfig['tiers'][$tier]['services'])) {
                $envConfig['tiers'][$tier]['services'][$service] = null;
            }

        }

        yaml_emit_file($fileName, $envConfig);

        $this->printLine("Service added to environment", 0, true);

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

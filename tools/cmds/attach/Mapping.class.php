<?php

namespace Tools\Attach;

use Tools\Tool;

class Mapping extends Tool{

    public function run() {
        $env = $this->args['environment'];
        if (strpos($env, '-') !== false) {
            $env = explode('-', $env);
            $tier = $this->formatArgument($env[1]);
            $env = $this->formatArgument($env[0]);
        } elseif(!$this->quiet){
            $env = $this->formatArgument($env);
            $tier = $this->formatArgument($this->promptForInput("Tier", true));
        } else {
            $this->printLine("Missing Tier", 0, true);
            exit;
        }

        $fileName = 'config/' . $env . '.yaml';
        if (!file_exists($fileName)) {
            $this->printLine("Environment not found", 0, true);
        }

        $envConfig = yaml_parse_file($fileName);

        if (
            !array_key_exists('tiers', $envConfig) ||
            !is_array($envConfig['tiers']) ||
            !array_key_exists($tier, $envConfig['tiers']) ||
            !is_array($envConfig['tiers'][$tier])
        ) {
            $this->printLine("Tier not found", 0, true);
        }

        if (!array_key_exists('mappings', $envConfig['tiers'][$tier])) {
            $envConfig['tiers'][$tier]['mappings'] = array();
        }

        $host = $this->args['resource'];
        $remove = array('https://', 'http://');
        $host = str_replace($remove, "", $host);
        if (substr($host, 0, 4) === 'api.' || substr($host, 0, 4) === 'api-') {
            $host = explode('.', $host);
            array_shift($host);
            $host = implode('.', $host);
        }
        $host = explode('/', $host);
        $host = $host[0];

        if (!in_array($host, $envConfig['tiers'][$tier]['mappings'])) {
            array_push($envConfig['tiers'][$tier]['mappings'], $host);
        }

        yaml_emit_file($fileName, $envConfig);
        $this->printLine("Mapping added", 0, true);

    }

    protected function formatArgument($name) {
        return preg_replace('/[^a-z]/','', strtolower($name));
    }

}

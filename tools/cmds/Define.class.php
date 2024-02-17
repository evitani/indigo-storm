<?php

namespace Tools;

class Define extends Tool {

    protected $argMap = array(
        'definitionName' => true,
        'value' => true
    );

    public function run(){
        $service = $this->_getService($this->args['definitionName']);
        $definitionName = $this->_getName($this->args['definitionName']);
        $fullDefinition = strtoupper($service . "_" . $definitionName);
        $value = $this->_generateRealValue($this->args['value']);
        $this->_writeToYaml($service, $definitionName, $value);
        $this->_writeTools();
        $this->printLine("Definition $fullDefinition added!", 0, true);
    }

    protected function _generateRealValue($value) {
        if (strtolower($value) === 'true' || strtolower($value) === 'false') {
            return strtolower($value) === 'true';
        }
        if (is_numeric($value)) {
            if (intval($value) != floatval($value)) {
                return floatval($value);
            } else {
                return intval($value);
            }
        }
        return $value;
    }

    protected function _getName($definition) {
        if (strpos($definition, '_') !== false) {
            $name = explode('_', $definition);
        } elseif (strpos($definition, '/') !== false) {
            $name = explode('/', $definition);
        }
        array_shift($name);
        return strtoupper(implode("_", $name));
    }

    protected function _getService($definition) {
        if (strpos($definition, '_') !== false) {
            $service = explode('_', $definition)[0];
        } elseif (strpos($definition, '/') !== false) {
            $service = explode('/', $definition)[0];
        } else {
            $this->printLine('ERR: Definition name must define a service', 0, true);
            exit;
        }
        $service = trim($service);
        $service = $this->_compareService($service);
        return $service;
    }

    protected function _compareService($service) {
        $serviceUc = strtoupper($service);
        $return = null;
        $dir = opendir('src');
        while (false !== ($file = readdir($dir))){
            if(
                ($file != '.') &&
                ($file != '..') &&
                is_dir('src' . '/' . $file) &&
                file_exists('src/' . $file . '/service.yaml')
            ){
                if ($service == $file || $serviceUc == strtoupper($file)) {
                    $return = $file;
                }
            }
        }
        closedir($dir);
        if (is_null($return)) {
            $this->printLine('ERR: Failed to find service for definition', 0, true);
            exit;
        } else {
            return $return;
        }
    }

    protected function _writeToYaml($service, $definition, $value) {
        $serviceDefinition = yaml_parse_file('src/' . $service . '/service.yaml');
        if(!array_key_exists('definitions', $serviceDefinition)) {
            $serviceDefinition['definitions'] = [];
        }
        $serviceDefinition['definitions'][$definition] = $value;
        yaml_emit_file('src/' . $service . '/service.yaml', $serviceDefinition);
    }

    protected function _writeTools() {
        $toolFile = "<?php" . PHP_EOL . "// Managed by the Indigo Storm developer tool" . PHP_EOL . PHP_EOL;
        unlink('tools/definitions.php');

        $dir = opendir('src');
        while (false !== ($file = readdir($dir))){
            if(
                ($file != '.') &&
                ($file != '..') &&
                is_dir('src' . '/' . $file) &&
                file_exists('src/' . $file . '/service.yaml')
            ){
                $sdef = yaml_parse_file('src/' . $file . '/service.yaml');
                if (array_key_exists('definitions', $sdef)) {
                    foreach ($sdef['definitions'] as $definition => $value) {
                        $definition = strtoupper($file . '_' . $definition);
                        if (is_string($value)) {
                            $value = "'" . str_replace("'", "\'", $value) . "'";
                        }
                        if (is_bool($value)) {
                            $value = $value ? 'true' : 'false';
                        }
                        $toolFile .= "define('$definition', $value);" . PHP_EOL;
                    }
                }
            }
        }
        closedir($dir);
        file_put_contents('tools/definitions.php', $toolFile);

    }
}

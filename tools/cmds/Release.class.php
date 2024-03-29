<?php

namespace Tools;

class Release extends Tool {

    protected $argMap = array(
        'service' => true,
        'tier' => false,
    );

    protected $flagMap = array(
        'archive' => 'archive',
        'b' => 'beta',
        'c' => 'composer',
        'd' => 'debug',
        'env' => 'env',
    );

    private $workingDir = '';
    private $workingDirNoSlash = '';

    private $gaeVpc = null;

    private $definitions = '';

    public function run(){

        $service = $this->args['service'];
        if (!file_exists('src/' . $service) || !file_exists('src/' . $service . '/service.yaml')) {
            $this->printLine('Invalid service, try upgrading if present', 0, true);
            exit;
        }

        if (!array_key_exists('tier', $this->args)){
            $tier = TIER_RELEASE;
        } elseif (in_array($this->args['tier'], array(TIER_RELEASE, TIER_PRERELEASE, TIER_LOCAL))) {
            $tier = $this->args['tier'];
        } else {
            $this->printLine('Invalid tier, can\'t release.', 0, true);
            exit;
        }

        $this->printLine('Creating empty release');
        $this->_createFolder($service, $tier);

        $this->printLine('Copying files');

        $baseFiles = array('LICENSE.md', 'index.php', 'composer.json');
        foreach ($baseFiles as $baseFile) {
            $this->_copyFile($baseFile);
        }

        $baseDirs = array('app');
        foreach ($baseDirs as $baseDir) {
            $this->_copyDirectory($baseDir);
        }

        mkdir($this->workingDir . 'src');
        $this->_copyDirectory('src/' . $service);
        $this->_serialiseServiceYaml($service);

        $this->printLine('Writing config files');
        mkdir($this->workingDir . 'config');
        $this->_reviewConfigs($service, $tier);
        $this->_globalConfig($service);

        $this->printLine('Collating interfaces');
        mkdir($this->workingDir . 'services');
        $this->_interfaces();

        $this->printLine('Writing definitions');
        $this->_writeDefinitions();

        if (array_key_exists('composer', $this->flags)){
            $this->_composer();
        }

        if (!array_key_exists('debug', $this->flags)){
            $this->printLine('Minimising PHP');
            $this->_minimise();
        }

        if (array_key_exists('env', $this->flags)) {
            $this->printLine('Building environment');
            $this->_envContent($service);
        }

        $this->_removeEmptySubFolders($this->workingDirNoSlash);

        $this->printLine('Release built!', 0, true);
    }

    private function _writeDefinitions() {
        $file = $this->workingDir . 'app/definitions.inc.php';
        file_put_contents($file, $this->definitions, FILE_APPEND);
    }

    private function _serialiseServiceYaml($service) {
        $baseFile = $this->workingDir . 'src/' . $service . '/service.';
        $serviceDefinition = yaml_parse_file($baseFile . 'yaml');

        if (array_key_exists('definitions', $serviceDefinition)) {
            $this->_createDefinitions($serviceDefinition['definitions'], $service);
            unset($serviceDefinition['definitions']);
        }

        if (array_key_exists('route-defaults', $serviceDefinition)) {
            foreach ($serviceDefinition['routes'] as $routeUrl => $routeConfig){
                $serviceDefinition['routes'][$routeUrl] = array_merge(
                    $serviceDefinition['route-defaults'],
                    $routeConfig
                );
            }
            unset($serviceDefinition['route-defaults']);
        }

        file_put_contents($baseFile . 'ser', serialize($serviceDefinition));
        unlink($baseFile . 'yaml');

    }

    private function _createDefinitions($definitions, $service) {
        $entries = '';
        $service = trim(strtoupper($service));
        foreach ($definitions as $definition => $value) {
            if (is_string($value)) {
                $value = "'" . str_replace("'", "\'", $value) . "'";
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $definition = $service . '_' . trim(strtoupper($definition));
            $entries .= "define('$definition', $value);" . PHP_EOL;
        }
        $this->definitions .= $entries;
    }

    private function _envContent($service) {

        switch (strtolower($this->flags['env'])) {
            case 'gae':
                $this->_envContentGae($service);
                break;
            case 'apache':
                $this->_envContentApache();
                break;
        }

    }

    private function _envContentGae($service) {

        $serviceId = strtolower(substr($service, 0, 1));

        if($service === strtoupper($service)) {
            $serviceId = strtolower($service);
        } else {
            for($i = 1; $i < strlen($service); $i++){
                if(substr($service, $i, 1) === strtoupper(substr($service, $i, 1))){
                    $serviceId .= "-";
                }
                $serviceId .= strtolower(substr($service, $i, 1));
            }
        }

        $appyamlFile = fopen($this->workingDir . "app.yaml", "w");
        $appyamlContent = "# Indigo Storm app.yaml file" . PHP_EOL;
        $appyamlContent .= "# Auto-generated by the Indigo Storm CLI" . PHP_EOL . PHP_EOL;
        $appyamlContent .= "runtime: php74" . PHP_EOL;
        $appyamlContent .= "service: " . $serviceId . PHP_EOL;
        $appyamlContent .= "env: standard" . PHP_EOL;
        if (!is_null($this->gaeVpc)) {
            $appyamlContent .= "vpc_access_connector:" . PHP_EOL;
            $appyamlContent .= "  name: $this->gaeVpc" . PHP_EOL;
        }
        fwrite($appyamlFile, $appyamlContent);
        fclose($appyamlFile);

    }

    private function _envContentApache() {
        $htaccessFile = fopen($this->workingDir . ".htaccess", "w");
        $htaccessContent = "# Indigo Storm htaccess file" . PHP_EOL;
        $htaccessContent .= "# Auto-generated by the Indigo Storm CLI" . PHP_EOL . PHP_EOL;
        $htaccessContent .= "RewriteEngine On" . PHP_EOL;
        $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-f" . PHP_EOL;
        $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-d" . PHP_EOL;
        $htaccessContent .= "RewriteRule ^ index.php [QSA,L]" . PHP_EOL;
        fwrite($htaccessFile, $htaccessContent);
        fclose($htaccessFile);
    }

    private function _minimise($dir = null) {
        if (is_null($dir)) $dir = $this->workingDir;
        foreach (scandir($dir) as $file) {
            if (is_dir($dir . '/' . $file) && $file != "." && $file != "..") {
                $this->_minimise($dir . '/' . $file);
            } elseif (substr($file, -4) === '.php') {
                $minified = php_strip_whitespace($dir . '/' . $file);
                file_put_contents($dir . '/' . $file, $minified);
            }
        }
    }

    private function _composer() {
        $this->printLine('Running composer install (this may take a moment)', 0, true);
        shell_exec("cd {$this->workingDir};composer install -q");
        unlink($this->workingDir . 'composer.json');
        unlink($this->workingDir . 'composer.lock');
    }

    private function _interfaces() {
        $copyList = array();
        foreach (scandir('src') as $srcDir) {
            if (
                is_dir('src/' . $srcDir) &&
                $srcDir != "." && $srcDir != ".." &&
                file_exists('src/' . $srcDir . '/Interfaces')
            ) {
                $copyList[$srcDir] = array();
                foreach (scandir('src/' . $srcDir . '/Interfaces') as $interface) {
                    if (substr($interface, -19) === 'Interface.class.php') {
                        array_push($copyList[$srcDir], $interface);
                    }
                }
            }
        }

        foreach ($copyList as $copyService => $copyInterfaces) {
            if (count($copyInterfaces) > 0) {

                $interfaceDefinitions = yaml_parse_file(
                    'src/' . $copyService . '/service.yaml'
                );
                if (array_key_exists('definitions', $interfaceDefinitions)) {
                    $this->_createDefinitions($interfaceDefinitions['definitions'], $copyService);
                }
                unset($interfaceDefinitions);

                mkdir($this->workingDir . 'services/' . $copyService);
                foreach ($copyInterfaces as $copyInterface) {
                    copy(
                        'src/' . $copyService . '/Interfaces/' . $copyInterface,
                        $this->workingDir . 'services/' . $copyService . '/' . $copyInterface
                    );
                }
            }
        }
    }

    private function _globalConfig($service) {
        $lite = false;
        if (file_exists('config/global.yaml')) {
            $global = yaml_parse_file('config/global.yaml');
            $lite = array_key_exists('lite', $global) ? $global['lite'] : false;
        }
        file_put_contents($this->workingDir . 'config/global.ser', serialize(array(
            'devmode' => false,
            'lite' => $lite,
            'service' => $service
        )));
    }

    private function _reviewConfigs($service, $tier) {
        $domains = array();

        $configs = scandir('config');
        foreach ($configs as $config) {

            if (substr($config, -5) === '.yaml') {
                $include = false;
                $configArray = yaml_parse_file('config/' . $config);

                if (
                    array_key_exists('services', $configArray) &&
                    array_key_exists($service, $configArray['services'])
                ) {
                   $include = '*';

                   if (array_key_exists('tiers', $configArray)) {
                       foreach ($configArray['tiers'] as $configTier => $configTierConfig) {
                           if (array_key_exists('mappings', $configTierConfig)) {
                               foreach ($configTierConfig['mappings'] as $mapping) {
                                   $domains[$mapping] = array(
                                       'env' => str_replace('.yaml', '', $config),
                                       'tier' => $configTier
                                   );
                               }
                           }
                       }
                   }

                } elseif (
                    array_key_exists('tiers', $configArray) &&
                    array_key_exists($tier, $configArray['tiers']) &&
                    array_key_exists('services', $configArray['tiers'][$tier]) &&
                    array_key_exists($service, $configArray['tiers'][$tier]['services'])
                ) {
                    $include = $tier;

                    if (array_key_exists('mappings', $configArray['tiers'][$tier])) {
                        foreach ($configArray['tiers'][$tier]['mappings'] as $mapping) {
                            $domains[$mapping] = array(
                                'env' => str_replace('.yaml', '', $config),
                                'tier' => $tier
                            );
                        }
                    }

                }

                if ($include !== false) {
                    if ($include === '*') {
                        $include = array_keys($configArray['tiers']);
                    } else {
                        $include = array($include);
                    }

                    $strippedConfig = $configArray;
                    unset($strippedConfig['tiers']);
                    foreach($include as $includeTier) {
                        $preppedConfig = $this->_deepMerge($strippedConfig, $configArray['tiers'][$includeTier]);
                        if (array_key_exists('mappings', $preppedConfig)) {
                            unset($preppedConfig['mappings']);
                        }
                        foreach ($preppedConfig['services'] as $serviceName => $details) {
                            if ($serviceName !== $service) {
                                unset($preppedConfig['services'][$serviceName]);
                            }
                        }

                        if (
                            array_key_exists('gae', $preppedConfig) &&
                            array_key_exists('database', $preppedConfig) &&
                            array_key_exists('vpcConnector', $preppedConfig['database'])
                        ) {
                            $vpc = 'projects/' . $preppedConfig['gae']['project'];
                            $vpc .= '/locations/' . $preppedConfig['gae']['location'];
                            $vpc .= '/connectors/' . $preppedConfig['database']['vpcConnector'];
                            if (is_null($this->gaeVpc)) {
                                $this->gaeVpc = $vpc;
                            } else {
                                $this->printLine('Multiple VPCs invoked, split release.', 0, true);
                                exit;
                            }
                        }

                        $configFile = fopen(
                            $this->workingDir .
                            "config/" .
                            str_replace('.yaml', '', $config) .
                            "-" .
                            $includeTier .
                            ".ser",
                            "w");
                        $configContent = serialize($preppedConfig) . PHP_EOL;
                        fwrite($configFile, $configContent);
                        fclose($configFile);
                    }
                }

            }
        }

        $domainFile = fopen($this->workingDir . "config/domains.ser", "w");
        $domainContent = serialize($domains) . PHP_EOL;
        fwrite($domainFile, $domainContent);
        fclose($domainFile);


    }

    private function _createFolder($service, $tier){
        if (!file_exists('releases')) {
            mkdir('releases');
        }

        if (!file_exists('releases/' . $tier)) {
            mkdir('releases/' . $tier);
        }

        $targetDir = 'releases/' . $tier . '/' . $service;
        $this->workingDirNoSlash = $targetDir;
        $this->workingDir = $targetDir . '/';

        if (!file_exists($targetDir)) {
            mkdir($targetDir);
        } else {
            $di = new \RecursiveDirectoryIterator($targetDir, \FilesystemIterator::SKIP_DOTS);
            $ri = new \RecursiveIteratorIterator($di, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach($ri as $file){
                $file->isDir() ? rmdir($file) : unlink($file);
            }
            foreach (array('.zip', '.tar.gz') as $packExt) {
                if (file_exists('releases/' . $tier . '/' . $service . $packExt)) {
                    unlink('releases/' . $tier . '/' . $service . $packExt);
                }
            }
        }
    }

    private function _copyFile($file){
        if (file_exists($file)){
            copy($file, $this->workingDir . $file);
        }
    }

    private function _copyDirectory($dir, $dest = null){

        if(is_null($dest)){
            $destination = $this->workingDir . $dir;
        }else{
            $destination = $this->workingDir . $dest;
        }

        $this->_recurseCopy($dir, $destination);

    }

    private function _recurseCopy($src, $dst){
        $dir = opendir($src);
        mkdir($dst);
        while (false !== ($file = readdir($dir))){
            if(($file != '.') && ($file != '..')){
                if(is_dir($src . '/' . $file)){
                    $this->_recurseCopy($src . '/' . $file, $dst . '/' . $file);
                }else{
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    private function _removeEmptySubFolders($path){
        $empty = true;
        foreach(glob($path . "/" . "*", GLOB_BRACE) as $file){
            $empty &= is_dir($file) && $this->_removeEmptySubFolders($file);
        }

        return $empty && rmdir($path);
    }

    private function _deepMerge($array1, $array2) {
        if (!is_array($array1)) {
            $array1 = array($array1);
        }
        if (!is_array($array2)) {
            $array2 = array($array2);
        }

        foreach ($array2 as $arrKey => $arrVal) {
            if (!array_key_exists($arrKey, $array1)) {
                $array1[$arrKey] = $arrVal;
            } elseif (is_array($array1[$arrKey]) || is_array($arrVal)) {
                $array1[$arrKey] = $this->_deepMerge($array1[$arrKey], $arrVal);
            } else {
                $array1[$arrKey] = $arrVal;
            }
        }

        return $array1;
    }

}

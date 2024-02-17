<?php

class Release extends Tool{
    protected $workingFolder;

    public function run($arguments = array(), $flags = array()){

        if(count($arguments) > 0){
            $serviceToBuild = $arguments[0];
        }else{
            $serviceToBuild = $this->promptForInput('Choose service to build', true);
        }

        $serviceReferences = $this->getServiceReferences(trim($serviceToBuild));

        $this->createReleaseFolder($serviceReferences['directoryName']);

        $php7 = false;
        if(array_key_exists('php7', $flags)){
            $php7 = true;
        }

        $autoSkipComposer = false;
        if(array_key_exists('env', $flags)){
            $this->printLine('Creating environment...');
            $this->generateEnvironmentHandler($flags['env'], $serviceReferences['serviceId'], $serviceReferences['directoryName']);
            $this->printLine('Creating environment... DONE');

            if($flags['env'] === 'gae73'){
                $autoSkipComposer = true;
                $php7 = true;
            }
        }

        $this->printLine('Copying files...');

        $this->printLine('LICENSE.md', 1);
        $this->copyFile('LICENSE.md');

        $this->printLine('index.php', 1);
        $this->copyFile('index.php');

        $this->printLine('/app', 1);
        $this->copyDirectory('app');

        $this->printLine('/src', 1);
        mkdir($this->workingFolder . "src");
        $this->copyDirectory('src/' . $serviceReferences['directoryName']);

        $this->printLine('config', 1);
        mkdir($this->workingFolder . "config");
        if(file_exists('config/domains.php')){
            $this->copyFile('config/domains.php');
        }

        $this->printLine('Copying files... DONE');

        $this->printLine('Building config...');
        $this->buildGlobalConfigs($serviceReferences['directoryName']);
        $this->buildEnvConfigs($serviceReferences['directoryName']);
        $this->printLine('Building config... DONE');

        $this->printLine('Including interfaces...');
        $this->includeInterfaces();
        $this->printLine('Including interfaces... DONE');

        $this->printLine('Deleting vcs files...');
        $this->printLine('.git', 1);
        $this->deleteGit();
        $this->printLine('Deleting vcs files... DONE');

        if(array_key_exists('nocomposer', $flags) || $autoSkipComposer){
            $this->printLine('Skipping composer...');

            if(array_key_exists('includecomposer', $flags) || $autoSkipComposer){
                //We're not running composer, but composer json files are required
                $this->printLine('Including composer files', 1);

                if($php7){
                    $composerContent = json_decode(file_get_contents('composer.json'), true);
                    if(
                        isset($composerContent['config']) &&
                        isset($composerContent['config']['platform']) &&
                        isset($composerContent['config']['platform']['php'])
                    ){
                        unset($composerContent['config']);
                        $composerFile = fopen($this->workingFolder . "composer.json", "w");
                        $composerFileContent = json_encode($composerContent);
                        fwrite($composerFile, $composerFileContent);
                        fclose($composerFile);
                    }else{
                        $this->copyFile('composer.json');
                    }

                }else{
                    $this->copyFile('composer.json');
                }

            }else{
                $this->printLine('Deleting composer files', 1);
                $this->deleteFileByName($this->workingFolder, 'composer.json');
            }

            $this->printLine('Skipping composer... DONE');

        }else{
            $this->printLine('Installing composer dependencies...');
            $this->copyFile('composer.json');
            $this->printLine('composer install', 1);
            shell_exec("cd {$this->workingFolder};composer install -q");
            $this->printLine('composer update', 1);
            shell_exec("composer update -q");
            shell_exec("cd ../");
            $this->deleteFile($this->workingFolder . 'composer.lock');
            $this->deleteFileByName($this->workingFolder, 'composer.json');
            $this->printLine('Installing composer dependencies... DONE');
        }

        $this->printLine('Minifying PHP...');
        $this->minifyPhp($this->workingFolder);
        $this->printLine('Minifying PHP... DONE');

        if(file_exists($this->workingFolder . "Container.definition.php")){
            unlink($this->workingFolder . "Container.definition.php");
        }

        if(array_key_exists('compress', $flags)){
            $this->printLine('Compressing...');
            $loc = $this->compressRelease($serviceReferences['directoryName'], $flags['compress']);
            $this->printLine('Compressing... DONE');
            $this->printLine('Release built at ' . $loc);
        }else{
            $this->printLine('Release built in ' . substr($this->workingFolder, 0, strlen($this->workingFolder) - 1));
        }

    }

    private function includeInterfaces(){
        $interfaces = array();

        $this->printLine('services directory', 1);
        $releasedInterfaces = scandir('services');
        foreach($releasedInterfaces as $releasedInterface){
            if(is_dir('services/' . $releasedInterface) && substr($releasedInterface, 0, 1) !== '.'){
                $interfaces[$releasedInterface] = 'released';
            }
        }

        $this->printLine('src/*/Interfaces directories', 1);
        $workingInterfaces = scandir('src');
        foreach($workingInterfaces as $workingInterface){
            if(is_dir('src/' . $workingInterface) && substr($workingInterface, -1, 1) !== '.'){
                if(
                    !array_key_exists($workingInterface, $interfaces) &&
                    is_dir('src/' . $workingInterface . '/Interfaces') &&
                    $this->hasFiles('src/' . $workingInterface . '/Interfaces')
                ){
                    $interfaces[$workingInterface] = 'working';
                }
            }
        }

        $this->printLine('Publishing services', 1);
        mkdir($this->workingFolder . 'services');
        foreach($interfaces as $interface => $src){
            switch ($src){
                case 'released':
                    $this->copyDirectory('services/' . $interface);
                    break;
                case 'working':
                    $this->copyDirectory('src/' . $interface . '/Interfaces', 'services/' . $interface);
            }
        }

    }

    private function hasFiles($dir){
        foreach(scandir($dir) as $dirContent){
            if(substr($dirContent, 0, 1) !== '.'){
                return true;
            }
        }
    }

    protected function compressRelease($serviceName, $compressType){
        if($compressType === true){
            $compressType = 'tar';
        }
        $compression = strtolower($compressType);
        $sourceName = substr($this->workingFolder, 0, strlen($this->workingFolder) - 1);
        switch ($compression){
            case 'tar':
                $this->printLine('Creating tar.gz', 1);
                shell_exec('cd releases;tar -czf ' . $serviceName . '.tar.gz ' . $serviceName . ';cd ../');
                $this->printLine('Deleting source', 1);
                $this->deleteDir($this->workingFolder);

                return $sourceName . '.tar.gz';
                break;
            case 'zip':
                $this->printLine('Creating zip', 1);
                shell_exec('cd releases;zip -r ' . $serviceName . '.zip ' . $serviceName . ' -q;cd ../');
                $this->printLine('Deleting source', 1);
                $this->deleteDir($this->workingFolder);

                return $sourceName . '.zip';
                break;
            default:
                $this->printLine('Unrecognised compression type, not compressed', 1);
        }
    }

    private function deleteDir($dir){
        $di = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $ri = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::CHILD_FIRST);
        foreach($ri as $file){
            $file->isDir() ? rmdir($file) : unlink($file);
        }
        rmdir(substr($dir, 0, strlen($this->workingFolder) - 1));
    }

    protected function buildEnvConfigs($serviceName){

        $configs = scandir('config');

        foreach($configs as $config){

            if(substr($config, 0, 1) !== '.' && is_dir('config/' . $config)){
                if(file_exists('config/' . $config . "/release.config.php")){

                    $sharedEnvironmentConfig = array();
                    $environmentConfig = array();

                    include 'config/' . $config . "/release.config.php";
                    include 'config/' . $config . "/shared.config.php";

                    if(
                        $this->findServiceInConfig($serviceName, $sharedEnvironmentConfig) ||
                        $this->findServiceInConfig($serviceName, $environmentConfig)
                    ){
                        //This service is used by this environment, so include it in the release
                        $this->printLine($config . "... COPIED", 1);

                        mkdir($this->workingFolder . '/config/' . $config);
                        $this->copyFile('config/' . $config . "/shared.config.php");

                        $configFiles = scandir('config/' . $config);
                        foreach($configFiles as $configFile){
                            if(!is_dir($configFile) && substr($configFile, 0, 7) === 'release'){
                                $this->copyFile('config/' . $config . '/' . $configFile);
                            }
                        }
                    }
                }
            }
        }
    }


    private function findServiceInConfig($serviceName, $configArray){
        if(array_key_exists('services', $configArray) && is_array($configArray['services'])){
            return
                array_key_exists($serviceName, $configArray['services']) &&
                count($configArray['services'][$serviceName]) > 0;
        }else{
            return false;
        }
    }


    protected function buildGlobalConfigs($serviceName){

        $this->printLine('global.config.php', 1);
        include 'config/global.config.php';
        $builtGlobalConfig = array();
        foreach($globalEnvironmentConfig as $item => $value){
            if($item === 'services' || $item === 'middleware'){
                $builtGlobalConfig[$item] = array();
                foreach($value as $serviceId => $configValue){
                    if($serviceId === 'Core' || $serviceId === $serviceName){
                        $builtGlobalConfig[$item][$serviceId] = $configValue;
                    }
                }
            }else{
                $builtGlobalConfig[$item] = $value;
            }
        }
        $globalConfigFile = fopen($this->workingFolder . "config/global.config.php", "w");
        $globalConfigContent = "<?php" . PHP_EOL;
        $globalConfigContent .= "\$globalEnvironmentConfig =" . $this->stringifyArray($builtGlobalConfig) . ";";
        fwrite($globalConfigFile, $globalConfigContent);
        fclose($globalConfigFile);

        $this->printLine('service.config.php', 1);
        $serviceConfigFile = fopen($this->workingFolder . "config/service.config.php", "w");
        $serviceConfigContent = "<?php" . PHP_EOL;
        $serviceConfigContent .= "define('_RUNNINGSERVICE_', '$serviceName');";
        $serviceConfigContent .= "define('_DEVMODE_', false);";
        fwrite($serviceConfigFile, $serviceConfigContent);
        fclose($serviceConfigFile);

    }

    private function stringifyArray($arr){
        $returnString = "";
        foreach($arr as $key => $value){
            if(is_numeric($key)){
                $returnString .= "$key => ";
            }else{
                $returnString .= "\"$key\" => ";
            }

            if(is_array($value)){
                $returnString .= $this->stringifyArray($value) . ",";
            }else{
                if(is_numeric($returnString) || is_null($value) || is_bool($value)){
                    $returnString .= $value . ",";
                }else{
                    $returnString .= "\"$value\",";
                }
            }
        }

        return "array(" . $returnString . ")";
    }

    protected function deleteGit(){
        $dir = $this->workingFolder;
        $di = new RecursiveDirectoryIterator($dir);
        $ri = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::CHILD_FIRST);
        foreach($ri as $file){
            if(strpos($file, '.git') > 0 && substr($file, -1) !== '.'){
                $file->isDir() ? rmdir($file) : unlink($file);
            }
        }
    }

    protected function deleteFileByName($scope, $pattern){

        $dir = $scope;
        $di = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $ri = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::CHILD_FIRST);
        foreach($ri as $file){
            if(substr($file, -strlen($pattern)) === $pattern){
                unlink($file);
            }
        }
    }

    protected function deleteFile($file){
        unlink($file);
    }

    protected function minifyPhp($dir){
        $files = scandir($dir);
        foreach($files as $key => $value){
            $path = realpath($dir . '/' . $value);
            if(!is_dir($path) && substr($path, -4) === '.php'){
                $updatedFile = php_strip_whitespace($path);
                file_put_contents($path, $updatedFile);
            }elseif(is_dir($path) && $value != "." && $value != ".."){
                $this->minifyPhp($path);
            }
        }
    }

    protected function copyFile($file){
        copy($file, $this->workingFolder . $file);
    }

    protected function copyDirectory($dir, $dest = null){
        if(is_null($dest)){
            $destination = $this->workingFolder . $dir;
        }else{
            $destination = $this->workingFolder . $dest;
        }

        $this->recurseCopy($dir, $destination);
        $this->removeEmptySubFolders($destination);

    }

    private function recurseCopy($src, $dst){
        $dir = opendir($src);
        mkdir($dst);
        while (false !== ($file = readdir($dir))){
            if(($file != '.') && ($file != '..')){
                if(is_dir($src . '/' . $file)){
                    $this->recurseCopy($src . '/' . $file, $dst . '/' . $file);
                }else{
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    private function removeEmptySubFolders($path){
        $empty = true;
        foreach(glob($path . "/" . "*", GLOB_BRACE) as $file){
            $empty &= is_dir($file) && $this->removeEmptySubFolders($file);
        }

        return $empty && rmdir($path);
    }

    protected function generateEnvironmentHandler($env, $serviceId, $serviceName){

        $serviceHandlers = "";

        if(file_exists('src/' . $serviceName . '/Container.definition.php')){
            $containerDefinition = array();
            include 'src/' . $serviceName . '/Container.definition.php';
            if(
                array_key_exists(strtolower($env), $containerDefinition) &&
                array_key_exists('urlHandlers', $containerDefinition[strtolower($env)])
            ){
                $serviceHandlers = $containerDefinition[strtolower($env)]['urlHandlers'];
            }
        }

        switch (strtolower($env)){
            case 'apache':

                $this->printLine('Apache htaccess file', 1);
                $htaccessFile = fopen($this->workingFolder . ".htaccess", "w");
                $htaccessContent = "# Indigo Storm htaccess file" . PHP_EOL;
                $htaccessContent .= "# Auto-generated by the Indigo Storm developer tool" . PHP_EOL . PHP_EOL;
                $htaccessContent .= "RewriteEngine On" . PHP_EOL;
                $htaccessContent .= PHP_EOL;
                if($serviceHandlers !== ''){
                    $htaccessContent .= $serviceHandlers . PHP_EOL;
                }
                $htaccessContent .= PHP_EOL;
                $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-f" . PHP_EOL;
                $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-d" . PHP_EOL;
                $htaccessContent .= "RewriteRule ^ index.php [QSA,L]" . PHP_EOL;
                fwrite($htaccessFile, $htaccessContent);
                fclose($htaccessFile);

                break;

            case 'gae73':

                $this->printLine('Google App Engine 7.3 file', 1);
                $appyamlFile = fopen($this->workingFolder . "app.yaml", "w");
                $appyamlContent = "# Indigo Storm app.yaml file" . PHP_EOL;
                $appyamlContent .= "# Auto-generated by the Indigo Storm developer tool" . PHP_EOL . PHP_EOL;
                $appyamlContent .= "runtime: php73" . PHP_EOL;
                $appyamlContent .= "service: " . $serviceId . PHP_EOL;
                $appyamlContent .= "env: standard" . PHP_EOL;
                fwrite($appyamlFile, $appyamlContent);
                fclose($appyamlFile);

                break;

            case 'gae55':
            case 'gae':

                $this->printLine('Google App Engine file', 1);
                $appyamlFile = fopen($this->workingFolder . "app.yaml", "w");
                $appyamlContent = "# Indigo Storm app.yaml file" . PHP_EOL;
                $appyamlContent .= "# Auto-generated by the Indigo Storm developer tool" . PHP_EOL . PHP_EOL;
                $appyamlContent .= "runtime: php55" . PHP_EOL;
                $appyamlContent .= "service: " . $serviceId . PHP_EOL;
                $appyamlContent .= "env: standard" . PHP_EOL;
                $appyamlContent .= "handlers:" . PHP_EOL;
                if($serviceHandlers !== ''){
                    $appyamlContent .= $serviceHandlers . PHP_EOL;
                }
                $appyamlContent .= "  - url: /.*" . PHP_EOL;
                $appyamlContent .= "    script: index.php" . PHP_EOL;
                fwrite($appyamlFile, $appyamlContent);
                fclose($appyamlFile);

                $this->printLine('php.ini', 1);
                $phpiniFile = fopen($this->workingFolder . "php.ini", "w");
                $phpiniContent = "; Indigo Storm PHP ini file" . PHP_EOL;
                $phpiniContent .= "; Auto-generated by the Indigo Storm developer tool" . PHP_EOL . PHP_EOL;
                $phpiniContent .= "google_app_engine.enable_curl_lite = \"1\"" . PHP_EOL;
                $phpiniContent .= "google_app_engine.enable_functions = \"php_sapi_name\"" . PHP_EOL;
                fwrite($phpiniFile, $phpiniContent);
                fclose($phpiniFile);

                break;
            default:
                $this->printLine('Unrecognised environment, no handler added', 1);
        }
    }

    protected function getServiceReferences($serviceToBuild){

        if(substr($serviceToBuild, 0, 1) === strtoupper(substr($serviceToBuild, 0, 1))){
            //This is probably a directory name, create a service ID
            $directoryName = $serviceToBuild;

            $serviceId = strtolower(substr($directoryName, 0, 1));

            for($i = 1; $i < strlen($directoryName); $i++){
                if(substr($directoryName, $i, 1) === strtoupper(substr($directoryName, $i, 1))){
                    $serviceId .= "-";
                }
                $serviceId .= strtolower(substr($directoryName, $i, 1));
            }

        }else{
            //This is probably a service ID, create a directory name
            $serviceId = $serviceToBuild;
            $directoryName = str_replace("-", " ", $serviceId);
            $directoryName = ucwords($directoryName);
            $directoryName = str_replace(" ", "", $directoryName);
        }

        if(!is_dir('src/' . $directoryName)){
            $this->printLine('Failed to find service with this name');
            exit(1);
        }else{

            return array(
                'directoryName' => $directoryName,
                'serviceId'     => $serviceId,
            );

        }

    }

    protected function createReleaseFolder($serviceName){

        if(!is_dir('releases')){
            mkdir('releases');
        }

        if(!is_dir('releases/' . $serviceName)){

            mkdir('releases/' . $serviceName);

        }else{

            $dir = "releases/" . $serviceName;
            $di = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
            $ri = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::CHILD_FIRST);
            foreach($ri as $file){
                $file->isDir() ? rmdir($file) : unlink($file);
            }

        }

        foreach(array('zip', 'tar.gz') as $packExt){
            if(file_exists('releases/' . $serviceName . '.' . $packExt)){
                unlink('releases/' . $serviceName . '.' . $packExt);
            }
        }

        $this->workingFolder = "releases/" . $serviceName . "/";

    }

}

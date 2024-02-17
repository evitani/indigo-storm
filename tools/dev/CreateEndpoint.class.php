<?php

class CreateEndpoint extends Tool{

    protected $workingDirectory;

    protected $returnType = 'json';
    protected $interfaceOnly = 'false';
    protected $requiresAuth = '';

    public function run($arguments = array(), $flags = array()){

        $serviceToAddTo = "";
        $endpointToAdd = "";

        if(count($arguments) > 0){
            $serviceToAddTo = $arguments[0];
        }
        if(count($arguments) > 1){
            $endpointToAdd = $arguments[1];
        }

        if($serviceToAddTo === ''){
            $serviceToAddTo = $this->promptForInput('Service that requires endpoint', true);
        }

        if($endpointToAdd === ''){
            $endpointToAdd = $this->promptForInput('Endpoint URL', true);
        }

        if(array_key_exists('returns', $flags)){
            $returnType = strtolower($flags['returns']);
            $allowedReturnTypes = array('json', 'file');
            if(in_array($returnType, $allowedReturnTypes)){
                $this->returnType = $returnType;
            }
        }

        $serviceToAddTo = $this->formatName($serviceToAddTo);

        $formattedEndpointName = $this->formatName($endpointToAdd);

        if(array_key_exists('method', $flags)){
            $methodsRequired = explode(',', $flags['method']);
        }else{
            $methodsRequired = explode(',', $this->promptForInput('Methods required (comma separated)'));
        }

        if(array_key_exists('interfaceonly', $flags)){
            $this->interfaceOnly = 'true';
        }

        if(array_key_exists('auth', $flags)){
            $this->requiresAuth = $flags['auth'];
        }

        if($this->controllerExists($serviceToAddTo, $formattedEndpointName)){
            $this->printLine('Controller already exists');
            exit(1);
        }

        $this->printLine('Creating Controller...');
        $this->addController($formattedEndpointName, $serviceToAddTo, $endpointToAdd, $methodsRequired);
        $this->printLine('Creating Controller... DONE');

        $this->printLine('Adding endpoint to Service definition...');
        $this->addEndpoint($serviceToAddTo, $endpointToAdd, $formattedEndpointName, $methodsRequired);
        $this->printLine('Adding endpoint to Service definition... DONE');

        $this->printLine('Endpoint and associated controller created!');

    }

    private function controllerExists($service, $ctrl){
        return file_exists("src/$service/Controllers/{$ctrl}Controller.class.php");
    }

    private function addEndpoint($serviceName, $endpointToAdd, $ctrl, $methods){

        $serviceDefinitionFile = fopen("src/$serviceName/Service.definition.php", "r");

        $newDefinitionContent = "";

        $pastArrStart = false;

        while (($line = fgets($serviceDefinitionFile)) !== false){
            if(strpos($line, '$serviceDefinition') !== false){
                $pastArrStart = true;
            }

            if($pastArrStart && strpos($line, "'$endpointToAdd' =>") !== false){
                $this->printLine('Endpoint already in Service.definition.php');
                fclose($serviceDefinitionFile);
                exit;
            }

            if($pastArrStart && strpos($line, '%INSERTPOINT%') !== false){
                $newDefinitionContent .= "    '$endpointToAdd' => array(" . PHP_EOL;
                $newDefinitionContent .= "        'controller' => '{$ctrl}Controller'," . PHP_EOL;
                $newDefinitionContent .= "        'returns' => '{$this->returnType}'," . PHP_EOL;
                $newDefinitionContent .= "        'methods' => array(" . PHP_EOL;

                $allowedMethods = array('get', 'post');
                $activeMethods = array();

                foreach($methods as $methodRequired){
                    $method = trim($methodRequired);

                    if(in_array(strtolower($method), $allowedMethods)){
                        $this->printLine('Registering ' . strtoupper($method), 1);

                        $newDefinitionContent .= "            '$method' => array(" . PHP_EOL;
                        $newDefinitionContent .= "                0 => null," . PHP_EOL;
                        $newDefinitionContent .= "                // 1 => array('arg1')," . PHP_EOL;
                        $newDefinitionContent .= "                // TODO add all required argument definitions" . PHP_EOL;
                        $newDefinitionContent .= "            )," . PHP_EOL;

                        $activeMethods[] = strtolower($method);

                    }

                }

                $newDefinitionContent .= "        )," . PHP_EOL;

                $newDefinitionContent .= "        'interface-only' => {$this->interfaceOnly}," . PHP_EOL;

                $auths = explode(",", strtolower(str_replace(" ", "", $this->requiresAuth)));

                $newDefinitionContent .= "        'authentication' => array(" . PHP_EOL;

                foreach($allowedMethods as $allowedMethod){
                    if(in_array($allowedMethod, $auths)){
                        $newDefinitionContent .= "                '$allowedMethod' => true," . PHP_EOL;
                    }elseif(in_array($allowedMethod, $activeMethods)){
                        $newDefinitionContent .= "                '$allowedMethod' => false," . PHP_EOL;
                    }
                }

                $newDefinitionContent .= "        )," . PHP_EOL;

                $newDefinitionContent .= "     )," . PHP_EOL;
            }

            $newDefinitionContent .= $line;
        }

        fclose($serviceDefinitionFile);
        $serviceDefinitionFile = fopen("src/$serviceName/Service.definition.php", "w");
        fwrite($serviceDefinitionFile, $newDefinitionContent);
        fclose($serviceDefinitionFile);
    }

    private function addController($endpointName, $serviceName, $endpointUrl, $methodsRequired){

        $newCtrlFile = fopen("src/{$serviceName}/Controllers/{$endpointName}Controller.class.php", "w");

        $newCtrlContent = "<?php" . PHP_EOL . PHP_EOL;
        $newCtrlContent .= "namespace $serviceName\Controllers;" . PHP_EOL . PHP_EOL;
        $newCtrlContent .= "use Core\Controllers\BaseController;" . PHP_EOL . PHP_EOL;
        $newCtrlContent .= "/**  " . PHP_EOL;
        $newCtrlContent .= " * Class for handling requests to /{$endpointUrl}" . PHP_EOL;
        $newCtrlContent .= " * Built by the Indigo Storm developer tool" . PHP_EOL;
        $newCtrlContent .= " * @package $serviceName\Controllers" . PHP_EOL;
        $newCtrlContent .= " */  " . PHP_EOL;
        $newCtrlContent .= "class {$endpointName}Controller extends BaseController{" . PHP_EOL . PHP_EOL;

        foreach($methodsRequired as $methodRequired){
            $method = trim($methodRequired);

            $allowedMethods = array('get', 'post');

            if(in_array(strtolower($method), $allowedMethods)){
                $this->printLine('Adding ' . strtoupper($method) . ' handler', 1);

                $functionName = "handle" . ucwords($method);

                $newCtrlContent .= "    /**  " . PHP_EOL;
                $newCtrlContent .= "     * @param \$request  object  The request object from Slim" . PHP_EOL;
                $newCtrlContent .= "     * @param \$response object  The Slim response object " . PHP_EOL;
                $newCtrlContent .= "     * @param \$args     array   Array of arguments available from the request" . PHP_EOL;
                $newCtrlContent .= "     */  " . PHP_EOL;
                $newCtrlContent .= "    public function {$functionName}(\$request, \$response, \$args){" . PHP_EOL;
                $newCtrlContent .= "        // TODO handler code here" . PHP_EOL;
                $newCtrlContent .= "    }" . PHP_EOL . PHP_EOL;

            }
        }

        $newCtrlContent .= "}" . PHP_EOL;

        fwrite($newCtrlFile, $newCtrlContent);
        fclose($newCtrlFile);

    }

    private function formatName($name){
        $name = str_replace("-", ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '', $name);

        return $name;
    }
}

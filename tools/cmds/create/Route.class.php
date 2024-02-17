<?php

namespace Tools\Create;

use Tools\Tool;

class Route extends Tool {

    public function run() {

        $service = $this->args['argument1'];
        if (strpos($service, '/') !== false) {
            $service = explode('/', $service);
            $routeName = $service[1];
            $routeArgs = $service;
            array_shift($routeArgs);
            array_shift($routeArgs);
            $service = $service[0];
        } elseif (array_key_exists('argument2', $this->args)) {
            $routeName = $this->args['argument2'];
            if (strpos($routeName, '/') !== false) {
                $routeName = explode('/', $routeName);
                $routeArgs = $routeName;
                array_shift($routeArgs);
                $routeName = $routeName[0];
            } else {
                $routeArgs = array();
            }
        } else {
            $routeName = $this->promptForInput("Route name", 0, true);
            if (strpos($routeName, '/') !== false) {
                $routeName = explode('/', $routeName);
                $routeArgs = $routeName;
                array_shift($routeArgs);
                $routeName = $routeName[0];
            } else {
                $routeArgs = array();
            }
        }

        $service = $this->formatServiceName($service);
        $routeName = str_replace(" ", "-", strtolower($routeName));
        foreach ($routeArgs as &$routeArg) {
            $routeArg = str_replace(' ', '', $routeArg);
        }

        if (!array_key_exists('method', $this->flags)) {
            $method = $this->promptForInput("Method", true);
        } else {
            $method = $this->flags['method'];
        }
        $method = strtolower($method);

        if (!in_array($method, array(HTTP_METHOD_GET, HTTP_METHOD_POST, HTTP_METHOD_PUT, HTTP_METHOD_DELETE))) {
            $this->printLine('Method not supported', 0 ,true);
        }

        $baseDir = 'src/' . $service;
        $controllerName = $this->formatServiceName($routeName) . 'Controller';
        $controllerFile = $baseDir . '/Controllers/' . $controllerName . '.class.php';

        if (
            !file_exists($baseDir) ||
            !is_dir($baseDir) ||
            !file_exists($baseDir . '/service.yaml')
        ) {
            $this->printLine("Service not found or outdated configuration type", 0, true);
            exit;
        }

        $serviceConfig = yaml_parse_file($baseDir . '/service.yaml');

        if (!array_key_exists('routes', $serviceConfig)) {
            $this->printLine("Creating route array");
            $serviceConfig['routes'] = array();
        }
        if (!array_key_exists($routeName, $serviceConfig['routes'])) {
            $this->printLine("Creating $routeName array");
            $serviceConfig['routes'][$routeName] = array(
                'controller' => $controllerName,
                'methods' => array()
            );
        }
        if (!array_key_exists('methods', $serviceConfig['routes'][$routeName])) {
            $this->printLine("Creating $routeName methods array");
            $serviceConfig['routes'][$routeName]['methods'] = array();
        }
        if (!array_key_exists($method, $serviceConfig['routes'][$routeName]['methods'])) {
            $this->printLine("Creating $routeName $method array");
            $serviceConfig['routes'][$routeName]['methods'][$method] = array();
        }
        if (!array_key_exists('arguments', $serviceConfig['routes'][$routeName]['methods'][$method])) {
            $this->printLine("Creating $routeName $method arguments array");
            $serviceConfig['routes'][$routeName]['methods'][$method]['arguments'] = array();
        }

        $totalArgs = count($routeArgs);
        if (array_key_exists($totalArgs, $serviceConfig['routes'][$routeName]['methods'][$method]['arguments'])) {
            $this->printLine("Route with this method and arguments already exists", 0, true);
            exit;
        }

        if(array_key_exists('interface', $this->flags)){
            $serviceConfig['routes'][$routeName]['methods'][$method]['interface-only'] = true;
        }

        if (array_key_exists('auth', $this->flags)) {
            $serviceConfig['routes'][$routeName]['methods'][$method]['authentication'] = true;
        }

        if (array_key_exists('access', $this->flags)) {
            $access = explode(',', str_replace(" ", "", strtoupper($this->flags['access'])));
            $serviceConfig['routes'][$routeName]['methods'][$method]['access-controls'] = $access;
        }

        if (array_key_exists('return', $this->flags)) {
            if (in_array($this->flags['return'], array(RETURN_JSON, RETURN_FILE))){
                $serviceConfig['routes'][$routeName]['methods'][$method]['returns'] = $this->flags['return'];
            }
        }

        if ($totalArgs === 0) {
            $argDefinition = null;
        } elseif ($totalArgs === 1) {
            $argDefinition = $routeArgs[0];
        } else {
            $argDefinition = $routeArgs;
        }
        $serviceConfig['routes'][$routeName]['methods'][$method]['arguments'][$totalArgs] = $argDefinition;

        $this->printLine("Saving service config");
        yaml_emit_file($baseDir . '/service.yaml', $serviceConfig);

        if (!file_exists($controllerFile)) {
            $this->addController($this->formatServiceName($routeName), $service, $routeName, array($method));
        }

    }

    protected function formatServiceName($input) {

        $input = str_replace("-", " ", $input);
        if (strtoupper($input) !== $input){
            $input = ucwords($input);
        }
        $input = str_replace(" ", "", $input);

        return $input;
    }

    private function addController($endpointName, $serviceName, $endpointUrl, $methodsRequired){

        $newCtrlFile = fopen("src/{$serviceName}/Controllers/{$endpointName}Controller.class.php", "w");

        $newCtrlContent = "<?php" . PHP_EOL . PHP_EOL;
        $newCtrlContent .= "namespace $serviceName\Controllers;" . PHP_EOL . PHP_EOL;
        $newCtrlContent .= "use Core\Controllers\BaseController;" . PHP_EOL . PHP_EOL;
        $newCtrlContent .= "use Core\Routing\Request;" . PHP_EOL . PHP_EOL;
        $newCtrlContent .= "use Core\Routing\Response;" . PHP_EOL . PHP_EOL;
        $newCtrlContent .= "/**  " . PHP_EOL;
        $newCtrlContent .= " * Class for handling requests to /{$endpointUrl}" . PHP_EOL;
        $newCtrlContent .= " * Built by the Indigo Storm developer tool" . PHP_EOL;
        $newCtrlContent .= " * @package $serviceName\Controllers" . PHP_EOL;
        $newCtrlContent .= " */  " . PHP_EOL;
        $newCtrlContent .= "class {$endpointName}Controller extends BaseController{" . PHP_EOL . PHP_EOL;

        foreach($methodsRequired as $methodRequired){
            $method = trim($methodRequired);

            $allowedMethods = array(HTTP_METHOD_GET, HTTP_METHOD_POST, HTTP_METHOD_PUT, HTTP_METHOD_DELETE);

            if(in_array(strtolower($method), $allowedMethods)){
                $this->printLine('Adding ' . strtoupper($method) . ' handler', 0);

                $functionName = "handle" . ucwords(strtolower($method));

                $newCtrlContent .= "    /**  " . PHP_EOL;
                $newCtrlContent .= "     * @param \$request  Request  The request object" . PHP_EOL;
                $newCtrlContent .= "     * @param \$response Response  The response object" . PHP_EOL;
                $newCtrlContent .= "     * @param \$args     array   Array deprecated, use \$request->getArgs()" . PHP_EOL;
                $newCtrlContent .= "     */  " . PHP_EOL;
                $newCtrlContent .= "    public function {$functionName}(Request \$request, Response \$response, array \$args){" . PHP_EOL;
                $newCtrlContent .= "        // TODO handler code here" . PHP_EOL;
                $newCtrlContent .= "    }" . PHP_EOL . PHP_EOL;

            }
        }

        $newCtrlContent .= "}" . PHP_EOL;

        fwrite($newCtrlFile, $newCtrlContent);
        fclose($newCtrlFile);

    }


}

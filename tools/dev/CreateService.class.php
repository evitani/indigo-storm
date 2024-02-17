<?php

class CreateService extends Tool{

    protected $workingDirectory;

    protected $includeSamples = true;

    public function run($arguments = array(), $flags = array()){

        if(count($arguments) > 0){
            $newServiceName = $arguments[0];
        }else{
            $newServiceName = $this->promptForInput('Service name', true);
        }

        $newServiceName = $this->formatName($newServiceName);

        $this->workingDirectory = 'src/' . $newServiceName;

        if(is_dir($this->workingDirectory) && !$this->dirEmpty($this->workingDirectory)){
            $this->printLine('A service with this name already exists');
            exit(1);
        }elseif(!is_dir($this->workingDirectory)){
            mkdir($this->workingDirectory);
        }

        if(array_key_exists('nosamples', $flags)){
            $this->includeSamples = false;
        }

        $this->printLine('Creating directory structure...');
        $this->buildDirectories();
        $this->printLine('Creating directory structure... DONE');

        $this->printLine('Creating definition files...');
        $this->printLine('Service.definition.php', 1);
        $this->createServiceDefinition($newServiceName);
        $this->printLine('Includes.definition.php', 1);
        $this->createIncludesDefinition($newServiceName);
        $this->printLine('Container.definition.php', 1);
        $this->createContainerDefinition($newServiceName);
        $this->printLine('Creating definition files... DONE');

        if($this->includeSamples){
            $this->printLine('Creating sample functionality...');
            $this->printLine($newServiceName . '.class.php', 1);
            $this->createSampleModel($newServiceName);
            $this->printLine('HelloWorldController.class.php', 1);
            $this->createSampleCtrl($newServiceName);
            $this->printLine('Creating sample functionality... DONE');
        }

        $this->printLine("Service created!");
    }

    private function createSampleCtrl($serviceName){

        $sampleCtrlFile = fopen($this->workingDirectory . "/Controllers/HelloWorldController.class.php", "w");

        $sampleCtrlContent = "<?php" . PHP_EOL . PHP_EOL;
        $sampleCtrlContent .= "namespace $serviceName\Controllers;" . PHP_EOL . PHP_EOL;
        $sampleCtrlContent .= "use Core\Controllers\BaseController;" . PHP_EOL . PHP_EOL;
        $sampleCtrlContent .= "/**  " . PHP_EOL;
        $sampleCtrlContent .= " * Example controller built by the Indigo Storm developer tool" . PHP_EOL;
        $sampleCtrlContent .= " * @package $serviceName\Controllers" . PHP_EOL;
        $sampleCtrlContent .= " * @copyright Evitani Limited 2019" . PHP_EOL;
        $sampleCtrlContent .= " * @license MIT License (see LICENSE.md)" . PHP_EOL;
        $sampleCtrlContent .= " */  " . PHP_EOL;
        $sampleCtrlContent .= "class HelloWorldController extends BaseController{" . PHP_EOL . PHP_EOL;
        $sampleCtrlContent .= "    /**  " . PHP_EOL;
        $sampleCtrlContent .= "     * @param \$request  object  The request object from Slim" . PHP_EOL;
        $sampleCtrlContent .= "     * @param \$response object  The Slim response object " . PHP_EOL;
        $sampleCtrlContent .= "     * @param \$args     array   Array of arguments available from the request" . PHP_EOL;
        $sampleCtrlContent .= "     * @return array            An array of sample content that will be added to the response" . PHP_EOL;
        $sampleCtrlContent .= "     */  " . PHP_EOL;
        $sampleCtrlContent .= "    public function handleGet(\$request, \$response, \$args){" . PHP_EOL . PHP_EOL;
        $sampleCtrlContent .= "        //Return an array which will be converted to JSON by Indigo Storm" . PHP_EOL;
        $sampleCtrlContent .= "        return array(" . PHP_EOL;
        $sampleCtrlContent .= "            \"serviceName\"       => \"$serviceName\"," . PHP_EOL;
        $sampleCtrlContent .= "            \"numberOfArguments\" => count(\$args)," . PHP_EOL;
        $sampleCtrlContent .= "            \"arguments\"         => \$args,  " . PHP_EOL;
        $sampleCtrlContent .= "            'generatedBy'       => \"Indigo Storm sample code\"" . PHP_EOL;
        $sampleCtrlContent .= "        );" . PHP_EOL . PHP_EOL;
        $sampleCtrlContent .= "    }" . PHP_EOL . PHP_EOL;
        $sampleCtrlContent .= "}" . PHP_EOL;

        fwrite($sampleCtrlFile, $sampleCtrlContent);
        fclose($sampleCtrlFile);

    }

    private function createSampleModel($serviceName){

        $sampleClassFile = fopen($this->workingDirectory . "/Models/$serviceName.class.php", "w");

        $sampleClassContent = "<?php" . PHP_EOL . PHP_EOL;
        $sampleClassContent .= "namespace $serviceName\Models;" . PHP_EOL;
        $sampleClassContent .= "use Core\Models\BaseModel;" . PHP_EOL;
        $sampleClassContent .= "/**" . PHP_EOL;
        $sampleClassContent .= " * Example model built by the Indigo Storm developer tool" . PHP_EOL;
        $sampleClassContent .= " * @package $serviceName\Controllers" . PHP_EOL;
        $sampleClassContent .= " * @copyright Evitani Limited 2019" . PHP_EOL;
        $sampleClassContent .= " * @license MIT License (see LICENSE.md)" . PHP_EOL;
        $sampleClassContent .= " */" . PHP_EOL;
        $sampleClassContent .= "class $serviceName extends BaseModel{" . PHP_EOL . PHP_EOL;
        $sampleClassContent .= "    /**" . PHP_EOL;
        $sampleClassContent .= "     * @var bool Allow items to be selected by ID as well as by name" . PHP_EOL;
        $sampleClassContent .= "     */" . PHP_EOL;
        $sampleClassContent .= "    protected \$allowEnumeration = false;" . PHP_EOL . PHP_EOL;
        $sampleClassContent .= "    /**" . PHP_EOL;
        $sampleClassContent .= "     * Runs when an object is instantiated to add custom DataTables to its schema" . PHP_EOL;
        $sampleClassContent .= "     */" . PHP_EOL;
        $sampleClassContent .= "    function configure(){" . PHP_EOL;
        $sampleClassContent .= "        // \$this->addDataTable('HelloWorld', DB2_VARCHAR_SHORT, DB2_VARCHAR_LONG);" . PHP_EOL;
        $sampleClassContent .= "        // Define any DataTables you require (Metadata is included by default)" . PHP_EOL;
        $sampleClassContent .= "    }" . PHP_EOL;
        $sampleClassContent .= "}" . PHP_EOL;

        fwrite($sampleClassFile, $sampleClassContent);
        fclose($sampleClassFile);

    }

    private function createContainerDefinition($serviceName){
        $serviceIncludesFile = fopen($this->workingDirectory . "/Container.definition.php", "w");

        $serviceIncludesContent = "<?php" . PHP_EOL;

        $serviceIncludesContent .= "// Container definition file for $serviceName" . PHP_EOL;
        $serviceIncludesContent .= "// Auto-generated by the Indigo Storm developer tool" . PHP_EOL . PHP_EOL;

        $serviceIncludesContent .= "\$containerDefinition = array(" . PHP_EOL;
        $serviceIncludesContent .= "    'interfaceRegistry' => array(" . PHP_EOL;
        $serviceIncludesContent .= "       // This section is managed by the Indigo Storm developer tool" . PHP_EOL;
        $serviceIncludesContent .= "       // %INSERTPOINT%" . PHP_EOL;
        $serviceIncludesContent .= "    )," . PHP_EOL;
        $serviceIncludesContent .= "    // 'apache' => array(" . PHP_EOL;
        $serviceIncludesContent .= "    //    'urlHandlers' => ''," . PHP_EOL;
        $serviceIncludesContent .= "    // )," . PHP_EOL;
        $serviceIncludesContent .= "    // Add other required container configuration here" . PHP_EOL;
        $serviceIncludesContent .= ");" . PHP_EOL;

        fwrite($serviceIncludesFile, $serviceIncludesContent);
        fclose($serviceIncludesFile);
    }

    private function createIncludesDefinition($serviceName){
        $serviceIncludesFile = fopen($this->workingDirectory . "/Includes.definition.php", "w");

        $serviceIncludesContent = "<?php" . PHP_EOL;

        $serviceIncludesContent .= "// Includes definition file for $serviceName" . PHP_EOL;
        $serviceIncludesContent .= "// Auto-generated by the Indigo Storm developer tool" . PHP_EOL . PHP_EOL;

        $serviceIncludesContent .= "\$includeDefinition = array(" . PHP_EOL;
        $serviceIncludesContent .= "      // 'example' => 'require'," . PHP_EOL;
        $serviceIncludesContent .= "      // Add required includes here" . PHP_EOL;
        $serviceIncludesContent .= ");" . PHP_EOL;

        fwrite($serviceIncludesFile, $serviceIncludesContent);
        fclose($serviceIncludesFile);
    }

    private function createServiceDefinition($serviceName){
        $serviceDefinitionFile = fopen($this->workingDirectory . "/Service.definition.php", "w");

        $serviceDefinitionContent = "<?php" . PHP_EOL;

        $serviceDefinitionContent .= "// Service definition file for $serviceName" . PHP_EOL;
        $serviceDefinitionContent .= "// Auto-generated by the Indigo Storm developer tool" . PHP_EOL . PHP_EOL;

        $serviceDefinitionContent .= "\$serviceDefinition = array(" . PHP_EOL;
        if($this->includeSamples){
            $serviceDefinitionContent .= "    'hello-world' => array(" . PHP_EOL;
            $serviceDefinitionContent .= "        'controller'  => 'HelloWorldController'," . PHP_EOL;
            $serviceDefinitionContent .= "        'returns'  => 'json'," . PHP_EOL;
            $serviceDefinitionContent .= "        'methods'=> array(" . PHP_EOL;
            $serviceDefinitionContent .= "            'get' => array(" . PHP_EOL;
            $serviceDefinitionContent .= "                0 => null," . PHP_EOL;
            $serviceDefinitionContent .= "                1 => 'foo'," . PHP_EOL;
            $serviceDefinitionContent .= "                2 => array('foo', 'bar')," . PHP_EOL;
            $serviceDefinitionContent .= "            )," . PHP_EOL;
            $serviceDefinitionContent .= "         )," . PHP_EOL;
            $serviceDefinitionContent .= "        'authentication'=> array(" . PHP_EOL;
            $serviceDefinitionContent .= "            'get' => false" . PHP_EOL;
            $serviceDefinitionContent .= "         )," . PHP_EOL;
            $serviceDefinitionContent .= "        'interface-only' => false," . PHP_EOL;
            $serviceDefinitionContent .= "    )," . PHP_EOL;
        }
        $serviceDefinitionContent .= "    // %INSERTPOINT%" . PHP_EOL;
        $serviceDefinitionContent .= "    // Add route configurations here" . PHP_EOL;
        $serviceDefinitionContent .= ");";

        fwrite($serviceDefinitionFile, $serviceDefinitionContent);
        fclose($serviceDefinitionFile);
    }

    private function buildDirectories(){
        $dirsToBuild = array(
            'Controllers',
            'Interfaces',
            'Middleware',
            'Models',
        );

        foreach($dirsToBuild as $dirToBuild){
            mkdir($this->workingDirectory . '/' . $dirToBuild);
        }
    }

    private function formatName($name){
        $name = str_replace("-", ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '', $name);

        return $name;
    }

    private function dirEmpty($dir){
        foreach(scandir($dir) as $dirContent){
            if(substr($dirContent, 0, 1) !== '.'){
                return false;
            }
        }

        return true;
    }

}

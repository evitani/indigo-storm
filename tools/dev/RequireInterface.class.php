<?php

class RequireInterface extends Tool{

    protected $workingDirectory;

    public function run($arguments = array(), $flags = array()){

        $serviceToAddTo = "";
        $interfaceToAdd = "";

        if(count($arguments) > 0){
            $serviceToAddTo = $arguments[0];
        }
        if(count($arguments) > 1){
            $interfaceToAdd = $arguments[1];
        }

        if($serviceToAddTo === ''){
            $serviceToAddTo = $this->promptForInput('Service that requires interface', true);
        }

        if($interfaceToAdd === ''){
            $interfaceToAdd = $this->promptForInput('Interface required', true);
        }

        $serviceToAddTo = $this->formatName($serviceToAddTo);

        if(!is_dir('src/' . $serviceToAddTo)){
            $this->printLine('Service not found');
            exit(1);
        }

        if(!file_exists("src/$serviceToAddTo/Container.definition.php")){
            $this->createContainerDefinition($serviceToAddTo);
        }

        $this->addInterface($serviceToAddTo, $interfaceToAdd);


    }

    private function addInterface($serviceName, $interfaceToAdd){
        $serviceContainerFile = fopen("src/$serviceName/Container.definition.php", "r");

        $newContainerContent = "";

        $pastArrStart = false;

        while (($line = fgets($serviceContainerFile)) !== false){
            if(strpos($line, 'interfaceRegistry') !== false){
                $pastArrStart = true;
            }

            if($pastArrStart && strpos($line, "'$interfaceToAdd' =>") !== false){
                $this->printLine('Interface already required');
                fclose($serviceContainerFile);
                exit;
            }

            if($pastArrStart && strpos($line, '%INSERTPOINT%') !== false){
                $newContainerContent .= "        '$interfaceToAdd' => '" . uniqid() . "'," . PHP_EOL;
            }

            $newContainerContent .= $line;
        }

        fclose($serviceContainerFile);
        $serviceContainerFile = fopen("src/$serviceName/Container.definition.php", "w");
        fwrite($serviceContainerFile, $newContainerContent);
        fclose($serviceContainerFile);
    }

    private function createContainerDefinition($serviceName){
        $serviceContainerFile = fopen("src/$serviceName/Container.definition.php", "w");

        $serviceContainerContent = "<?php" . PHP_EOL;

        $serviceContainerContent .= "// Container definition file for $serviceName" . PHP_EOL;
        $serviceContainerContent .= "// Auto-generated by the Indigo Storm developer tool" . PHP_EOL . PHP_EOL;

        $serviceContainerContent .= "\$containerDefinition = array(" . PHP_EOL;
        $serviceContainerContent .= "    'interfaceRegistry' => array(" . PHP_EOL;
        $serviceContainerContent .= "       // This section is managed by the Indigo Storm developer tool" . PHP_EOL;
        $serviceContainerContent .= "       // %INSERTPOINT%" . PHP_EOL;
        $serviceContainerContent .= "    )," . PHP_EOL;
        $serviceContainerContent .= "    // 'apache' => array(" . PHP_EOL;
        $serviceContainerContent .= "    //    'urlHandlers' => ''," . PHP_EOL;
        $serviceContainerContent .= "    // )," . PHP_EOL;
        $serviceContainerContent .= "    // Add other required container configuration here" . PHP_EOL;
        $serviceContainerContent .= ");" . PHP_EOL;

        fwrite($serviceContainerFile, $serviceContainerContent);
        fclose($serviceContainerFile);
    }

    private function formatName($name){
        $name = str_replace("-", ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '', $name);

        return $name;
    }

}
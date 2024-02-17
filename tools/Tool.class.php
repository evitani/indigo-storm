<?php

class Tool{
    protected $isQuiet = false;

    function runQuiet(){
        $this->isQuiet = true;
    }

    function processGlobalFlags($flags){
        foreach($flags as $flag => $flagVal){
            switch (strtoupper($flag)){
                case 'Q':
                    $this->runQuiet();
                    break;
            }
        }
    }

    function promptForInput($prompt, $required = false){

        if($this->isQuiet && $required){
            throw new \Exception('Cannot prompt, running quietly', 500);
        }elseif($this->isQuiet){
            return null;
        }

        $usePrompt = $prompt;
        if(!$required){
            $usePrompt .= " [optional]";
        }
        $usePrompt .= ": ";

        $returnValue = readline($usePrompt);

        if(trim($returnValue) == ""){
            $returnValue = null;
        }

        if(is_null($returnValue) && $required){
            $this->printLine('You must enter a value to continue', 1);

            return $this->promptForInput($prompt, $required);
        }else{
            return $returnValue;
        }
    }

    function printLine($lineToPrint, $level = 0){
        $printString = "";

        for($i = 0; $i < $level; $i++){
            $printString .= " ";
        }

        $printString .= $lineToPrint;

        echo $printString . PHP_EOL;
    }

    function run($arguments, $flags){

    }

}

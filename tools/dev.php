<?php

require_once 'app/definitions.inc.php';
require_once 'tools/Tool.class.php';

//Define a dynamic class loader for use loading tool.
function dynamic_loader($class){
    return new $class;
}

// Temporarily disabled while releases are sporadic
//if(floatval(IS_VERSION) < floatval(IS_MOSTRECENT)){
//    echo "WARN: Your version of Indigo Storm (" . IS_VERSION . ") is out of date, update to " . IS_MOSTRECENT . PHP_EOL;
//}

if(isset($argv[1])){

    $incomingArguments = $argv;
    array_shift($incomingArguments);
    array_shift($incomingArguments);

    $arguments = array();
    $flags = array();

    foreach($incomingArguments as $incomingArgument){
        if(substr($incomingArgument, 0, 1) === '-'){

            $flagVal = str_replace('-', '',
                                   str_replace('--', '', $incomingArgument)
            );

            $flagVal = explode('=', $flagVal);

            if(count($flagVal) === 2){
                $flags[$flagVal[0]] = $flagVal[1];
            }else{
                $flags[$flagVal[0]] = true;
            }

        }else{
            array_push($arguments, $incomingArgument);
        }
    }

    $toolToRun = str_replace(' ', '', ucwords(str_replace('-', ' ', $argv[1])));

    if(!file_exists('tools/dev/' . $toolToRun . '.class.php')){
        $tool = new Tool;
        $tool->printLine('Unrecognised command, run `man` to find commands');
        exit(1);
    }

    require_once 'tools/dev/' . $toolToRun . '.class.php';

    $tool = dynamic_loader($toolToRun);

    $tool->processGlobalFlags($flags);

    $tool->run($arguments, $flags);

}else{
    echo "You must specify a parameter when running this command." . PHP_EOL;
}

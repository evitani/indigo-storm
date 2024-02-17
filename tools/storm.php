<?php

if (!function_exists('yaml_parse_file') || intval(PHP_MAJOR_VERSION) < 7) {
    echo "PHP 7+ with the yaml extension is required" . PHP_EOL;
    exit;
}

if (count($argv) > 1) {

    require_once 'app/definitions.inc.php';
    require_once 'tools/Tool.class.php';

    $args = $argv;
    array_shift($args);
    $args = expandShorthand($args);
    $tool = ucwords($args[0]);

    if (file_exists('tools/cmds/' . $tool . '.class.php')) {
        require_once 'tools/cmds/' . $tool . '.class.php';
        $tool = tool_dynamic_loader('Tools\\' . $tool);
    } else {
        echo "Unrecognised command, run `help` to list commands" . PHP_EOL;
        exit;
    }

    array_shift($args);
    $processFlags = processFlags($args, $tool->getFlags());
    $tool->importArgs($processFlags['args']);
    $tool->importFlags($processFlags['flags']);

    $tool->run();

} else {
    echo "Command expects arguments, run `help` for more information". PHP_EOL;
}

function tool_dynamic_loader($class){
    return new $class;
}

function expandShorthand($args) {

    $arg1 = null;
    $arg2 = null;

    $singlePart = array(
        'i' => 'init',
        'r' => 'release',
        'h' => 'help',
    );

    $doublePart = array(
        'c' =>  array(
            1 => 'create',
            'r' => 'route',
            's' => 'service',
            'e' => 'environment'
        ),
        'a' => array(
            1 => 'attach',
            'm' => 'mapping',
            's' => 'service',
            'r' => 'route',
        ),
        'u' => array(
            1 => 'upgrade',
            's' => 'service',
            'e' => 'environment',
        ),
    );

    if (count($args) > 0){
        if (strlen($args[0]) === 1){
            $arg1 = strtolower($args[0]);
        }
        if (count($args) > 1 && strlen($args[1]) === 1) {
            $arg2 = strtolower($args[1]);
        }
    }

    if (is_null($arg2)) {
        if (array_key_exists($arg1, $singlePart)) {
            $args[0] = $singlePart[$arg1];
        }
        return $args;
    } elseif (array_key_exists($arg1, $doublePart) && array_key_exists($arg2, $doublePart[$arg1])) {
            $args[0] = $doublePart[$arg1][1];
            $args[1] = $doublePart[$arg1][$arg2];
            return $args;
    } else {
        return $args;
    }

}

function processFlags($args, $toolFlags = array()) {

    $globalFlags = array(
        'q' => 'quiet',
        'v' => 'verbose',
    );

    $matchFlags = array_merge($globalFlags, $toolFlags);
    $flags = array();

    $newArgs = array();

    foreach($args as $arg) {
        $storedArg = $arg;
        $arg = strtolower($arg);
        if (substr($arg, 0, 1) === '-' && substr($arg, 0, 2) !== '--'){
            foreach(str_split(substr($arg, 1), 1) as $flag){
                if(array_key_exists($flag, $matchFlags) && !array_key_exists($flag, $flags)){
                    $flags[$matchFlags[$flag]] = true;
                }
            }

        } elseif (substr($arg, 0, 2) === '--') {

            $strippedArg = explode('=', substr($arg, 2));

            if (array_key_exists($strippedArg[0], array_flip($matchFlags))) {
                if (count($strippedArg) === 2) {
                    $flags[$strippedArg[0]] = $strippedArg[1];
                } else {
                    $flags[$strippedArg[0]] = true;
                 }
            }


        } else {
            array_push($newArgs, $storedArg);
        }
    }

    return array(
        'flags' => $flags,
        'args' => $newArgs,
    );

}


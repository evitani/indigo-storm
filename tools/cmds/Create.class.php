<?php

namespace Tools;

class Create extends Tool {

    protected $argMap = array(
        'resourceType' => true,
        'argument1' => true,
        'argument2' => false,
    );

    protected $flagMap = array(
        'd' => 'db',
        'g' => 'gae',
        's' => 'ssl',
        'b' => 'bootstrap',
        'method' => 'method',
        'return' => 'return',
        'interface' => 'interface',
        'auth' => 'auth',
        'access' => 'access',
    );

    public function run(){
        $tool = ucwords($this->args['resourceType']);
        if (file_exists('tools/cmds/create/' . $tool . '.class.php')) {
            require_once 'tools/cmds/create/' . $tool . '.class.php';
            $tool = tool_dynamic_loader('Tools\\Create\\' . $tool);
            $tool->importFlags($this->flags);
            unset($this->args['resourceType']);
            $tool->importArgs($this->args);
            $tool->run();
        } else {
            echo "Unrecognised command $tool, run `help` to list commands" . PHP_EOL;
            exit;
        }
    }

}

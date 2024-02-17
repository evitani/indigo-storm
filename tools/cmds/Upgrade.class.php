<?php

namespace Tools;

class Upgrade extends Tool {

    protected $argMap = array(
        'resourceType' => true,
        'target' => true,
    );

    protected $flagMap = array(
    );

    public function run(){
        $tool = ucwords($this->args['resourceType']);
        if (file_exists('tools/cmds/upgrade/' . $tool . '.class.php')) {
            require_once 'tools/cmds/upgrade/' . $tool . '.class.php';
            $tool = tool_dynamic_loader('Tools\\Upgrade\\' . $tool);
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

<?php

namespace Tools;

class Attach extends Tool {

    protected $argMap = array(
        'resourceType' => true,
        'environment' => true,
        'resource' => true,
    );

    protected $flagMap = array(
        's' => 'snapshot',
        'i' => 'interface',
    );

    public function run(){
        $tool = ucwords($this->args['resourceType']);
        if (file_exists('tools/cmds/attach/' . $tool . '.class.php')) {
            require_once 'tools/cmds/attach/' . $tool . '.class.php';
            $tool = dynamic_loader('Tools\\Attach\\' . $tool);
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

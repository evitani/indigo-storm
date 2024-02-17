<?php

class Man extends Tool{

    public function run($arguments = array(), $flags = array()){
        $this->printLine('=============================');
        $this->printLine(' Indigo Storm Developer Tool ');
        $this->printLine('=============================');
        $this->printLine('Available commands:');
        $this->printLine('• create-service: build a service template', 1);
        $this->printLine('  options: [ServiceName]', 3);
        $this->printLine('  flags: --no-samples (don\'t include sample files)', 3);
        $this->printLine('• create-environment: set up a new environment', 1);
        $this->printLine('  options: [name] [tier]', 3);
        $this->printLine('  flags: --db (prompt for details and configure database)', 3);
        $this->printLine('• create-endpoint: add an endpoint to a service', 1);
        $this->printLine('  options: [ServiceName] [endpoint-url]', 3);
        $this->printLine('  flags: -returns (json or file)', 3);
        $this->printLine('         -method (comma-separated list of methods without spaces)', 3);
        $this->printLine('         --auth (comma-separated list of methods that require auth, no spaces)', 3);
        $this->printLine('         --interface-only (this endpoint is for interfaces only)', 3);
        $this->printLine('• require-interface: add an interface to a service\'s required list', 1);
        $this->printLine('  options: [ServiceName] [InterfaceName]', 3);
        $this->printLine('');
        $this->printLine('Global flags:');
        $this->printLine('• -q: don\'t prompt for inputs (all options must be included)', 1);
        $this->printLine('');
    }

}

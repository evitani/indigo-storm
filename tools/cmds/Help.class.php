<?php

namespace Tools;

class Help extends Tool {

    public function run(){

        $lines = [
            'Available Commands:',

            '`• init',
            '  Sets up a local development environment',
            '',

            '`• help',
            '  Displays available commands',
            '',

            '`• upgrade [service, environment] <target name>',
            '  Upgrades a pre-20.19 service or environment to the latest specification',
            '',

            '`• create environment <environment name> [local, prerelease, release]',
            '  Create a new environment/tier',
            '`  --ssl` force SSL on all requests in this environment',
            '`  --db` prompt for database credentials',
            '`  --gae` prompt for Google-specific configuration details (region, queue name, etc)',
            '',

            '`• create service <service name> [local, prerelease, release]',
            '  Create a new service',
            '',

            '`• create route <service url>/<route url>{/<route parameter name(s)>}',
            '  Create a new route (or new method on an existing route)',
            '`  --method=[GET, POST, PUT, DELETE]` the method this route will be accessible via',
            '`  --auth` enable API key authentication for the route',
            '`  --interface` designate route as interface-only',
            '`  --access=<group name(s)>` lock route down to users in a group (or comma-separated groups)',
            '',

            '`• attach service <environment name>-<environment tier> <service name>',
            '  Attach a service to an environment to include it in the deployment',
            '',

            '`• attach route <environment name>-<environment tier> <service url>/<route url>',
            '  Attach a route to an environment to make it accessible',
            '',

            '`• attach mapping <environment name>-<environment tier> <mapping url>',
            '  Attach a URL mapping to an environment for automatic environment selection',
            '',

            '`• define <service name>/<definition name> <value>',
            '  Define a constant for a given service, will be created as <SERVICE NAME>_<DEFINITION_NAME>',
            '',

            '`• undefine <service name>/<definition name>',
            '  Remove a previously-defined constant from the service',
            '',

            '`• release <service name>',
            '  Create release-ready files of a service, including all relevant environment configurations',
            '`  --tier=[local, prerelease, release]` the tier to create release files for',
            '`  --env=[gae,apache]` create platform-specific release files',
            '`  --debug` disable minification of PHP',
            '`  --composer` run composer-install as part of the release to include vendor files',

        ];

        foreach ($lines as $line) {
            if (substr($line, 0, 1) === '`') {
                $actualLine = substr($line, 1);
                if (strpos($actualLine, '`')) {
                    $line = "\e[38;5;105m" . str_replace('`', "\e[0m", $actualLine);
                } else {
                    $line = "\e[38;5;105m" . $actualLine . "\e[0m";
                }
            }
            $this->printLine($line, 0, true);
        }

    }
}

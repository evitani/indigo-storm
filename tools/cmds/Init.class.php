<?php

namespace Tools;

class Init extends Tool {

    public function run() {

        $this->printDialog("CLI Shortcut", "You can add a CLI shortcut that allows you to use the " .
                           "developer tool in future by calling `storm` rather than `./storm`. This works with bash " .
                           "and zsh terminals, but will require you restart your terminal before you can use it for " .
                           "the first time."
        );

        $didAddCli = false;
        if ($this->promptForInput("Add CLI shortcut", false, true)) {
            $this->addCliShortcut();
            $didAddCli = true;
        }

        $this->printDialog("Local Database Server", "You can the address and credentials for your local " .
                           "database server that you use during development. When you create new local environments, " .
                           "new users and databases will be generated automatically on your server."
        );

        if ($this->promptForInput("Add a local database server", false, true)) $this->addDb();

        $this->createEmptyDirectories();
        $this->createDevConfig();

        $this->printLine("" . PHP_EOL .
                         "    ____          ___                _____ __" . PHP_EOL .
                         "   /  _/___  ____/ (_)___ _____     / ___// /_____  _________ ___" . PHP_EOL .
                         "   / // __ \/ __  / / __ `/ __ \    \__ \/ __/ __ \/ ___/ __ `__ \\" . PHP_EOL .
                         " _/ // / / / /_/ / / /_/ / /_/ /   ___/ / /_/ /_/ / /  / / / / / /" . PHP_EOL .
                         "/___/_/ /_/\__,_/_/\__, /\____/   /____/\__/\____/_/  /_/ /_/ /_/" . PHP_EOL .
                         "                  /____/" . PHP_EOL . PHP_EOL .
                         "                    WELCOME TO INDIGO STORM!" . PHP_EOL .
                         ($didAddCli ?
                             "                  run `storm help` for support" . PHP_EOL :
                             "                 run `./storm help` for support" . PHP_EOL)
            , 0, true);
    }

    protected function createHtaccess() {
        $htaccessFile = fopen(".htaccess", "w");
        $htaccessContent = "# Indigo Storm htaccess file" . PHP_EOL;
        $htaccessContent .= "# Auto-generated by the Indigo Storm developer tool" . PHP_EOL . PHP_EOL;
        $htaccessContent .= "RewriteEngine On" . PHP_EOL;
        $htaccessContent .= PHP_EOL;
        $htaccessContent .= PHP_EOL;
        $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-f" . PHP_EOL;
        $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-d" . PHP_EOL;
        $htaccessContent .= "RewriteRule ^ index.php [QSA,L]" . PHP_EOL;
        fwrite($htaccessFile, $htaccessContent);
        fclose($htaccessFile);
    }

    protected function createEmptyDirectories() {
        $directories = array(
            'config',
            'src'
        );
        foreach($directories as $directory) {
            if (!file_exists($directory)) {
                mkdir($directory);
            }
        }
    }

    protected function createDevConfig() {
        if (!file_exists('config/global.yaml')){
            $devConfig = array(
                'devmode' => true,
                'lite'    => false,
            );
            yaml_emit_file('config/global.yaml', $devConfig);
        }
    }

    protected function addDb() {
        $ip = $this->promptForInput("Server address", true);
        $user = $this->promptForInput("Username", true);
        $password = $this->promptForInput("Password", true);
        $prefix = $this->promptForInput("Database prefix (default 'is')");
        if (is_null($prefix)) $prefix = "is";

        if (file_exists('tools/db.yaml')) {
            $dbs = yaml_parse_file('tools/db.yaml');
        } else {
            $dbs = array();
        }

        $dbs['local'] = array(
            'ip' => base64_encode($ip),
            'user' => base64_encode($user),
            'password' => base64_encode($password),
            'prefix' => base64_encode($prefix)
        );

        yaml_emit_file('tools/db.yaml', $dbs);

    }

    protected function addCliShortcut() {
        $possibleLocations = array(
            '~/.bashrc',
            '~/.zshrc'
        );

        foreach($possibleLocations as $possibleLocation) {
            $this->printLine("Trying to add shortcut to $possibleLocation");
            exec("FILE=$possibleLocation
            if test -f \"\$FILE\"; then
            echo 'alias storm=\"./storm\"' >> $possibleLocation
            fi");
        }
    }

}

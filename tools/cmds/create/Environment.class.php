<?php

namespace Tools\Create;

use Tools\Tool;

class Environment extends Tool {

    public function run() {

        $envName = $this->formatArgument($this->args['argument1']);
        $envTier = $this->formatArgument($this->args['argument2']);

        if (!in_array($envTier, array(TIER_RELEASE, TIER_PRERELEASE, TIER_LOCAL))) {
            $this->printLine('Tier not recognised', 0, true);
            exit;
        }

        $fileName = 'config/' . $envName . '.yaml';

        if (file_exists($fileName)) {
            $config = yaml_parse_file($fileName);
        } else {
            $config = array(
                'name' => $this->args['argument1'],
                'security' => array(
                    'forceSSL' => false
                ),
            );
        }

        if (!array_key_exists('tiers', $config)) {
            $config['tiers'] = array();
        }

        if (array_key_exists($envTier, $config['tiers'])) {
            $this->printLine('Environment and tier already exists', 0, true);
            exit;
        }

        $url = $this->promptForInput("Service URL (replace service ID with _SERVICE_)", false);

        $config['tiers'][$envTier] = array(
            'url' => is_null($url) ? "[ SERVICE URL ]" : $url,
            'security' => array(
                'globalSalt' => sha1(uniqid($envName . $envTier, true)),
                'forceSSL' => array_key_exists('ssl', $this->flags) && $this->flags['ssl']
            ),
        );

        if ( array_key_exists('gae', $this->flags) && (is_string($this->flags['gae']) || $this->flags['gae'])) {
            $gae = explode(',', $this->flags['gae']);
            if (count($gae) === 3) {
                $project = $gae[0];
                $location = $gae[1];
                $queue = $gae[2];
            } else {
                $project = $this->promptForInput("App Engine project ID", true);
                $location = $this->promptForInput("App Engine location", true);
                $queue = $this->promptForInput("Cloud Task Queue ID", true);
            }
            $config['tiers'][$envTier]['gae'] = array(
                'project' => $project,
                'location' => $location,
                'queue' => $queue
            );
        }

        if (array_key_exists('db', $this->flags)) {

            // Ask for custom DB details
            $db = array(
                "server" => $this->promptForInput("Database server", true),
                "db" => $this->promptForInput("Database name", true),
                "user" => $this->promptForInput("Database username", true),
                "password" => $this->promptForInput("Database password", true),
            );

            if (array_key_exists('gae', $this->flags)) {
                $connector = $this->promptForInput("VPC Connector");
                if (!is_null($connector)) {
                    $db['vpcConnector'] = $connector;
                }
            }

            $this->createConfigTable($db);
        } elseif (file_exists('tools/db.yaml')) {

            // Check if we can auto-setup a database
            $this->printLine("Attempting automatic database setup");
            $dbConfigs = yaml_parse_file('tools/db.yaml');
            if (array_key_exists($envTier, $dbConfigs)) {
                $db = $this->createDatabase($dbConfigs[$envTier], $envName, $envTier);
            }

        }

        if (!isset($db)) {
            $db = array(
                "server" => '[ SERVER ADDRESS ]',
                "db" => '[ DB NAME ]',
                "user" => '[ USERNAME ]',
                "password" => '[ PASSWORD ]',
            );
        }

        $config['tiers'][$envTier]['database'] = $db;

        yaml_emit_file($fileName, $config);

        $this->printLine("Environment created!", 0, true);

    }

    protected function createConfigTable($db){
        $dbcon = new \mysqli($db['server'], $db['user'], $db['password'], $db['db']);
        try{
            $dbcon->query("CREATE TABLE `_Config` (
                          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                          `objectName` varchar(64) NOT NULL DEFAULT '',
                          `objectVersion` varchar(128) NOT NULL DEFAULT '',
                          PRIMARY KEY (`id`),
                          UNIQUE KEY `objectName` (`objectName`)
                        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");
        } catch (\Exception $e) {
            $this->printLine("WARN: Database not configured", 0, true);
        }
    }

    protected function createDatabase($credentials, $name, $tier) {
        foreach ($credentials as $key => $credential) {
            $credentials[$key] = base64_decode($credential);
        }

        $dbname = implode("_", array($credentials['prefix'], $name, $tier));
        $dbuser = implode("_", array(
            substr($credentials['prefix'], 0, 3),
            substr($name, 0, 5),
            substr($tier, 0, 5)
        ));
        $dbpass = hash("sha256", uniqid($dbname . $dbuser, true));

        try {
            $db = new \mysqli($credentials['ip'], $credentials['user'], $credentials['password']);
            $queries = array(
                "CREATE DATABASE $dbname;",
                "CREATE USER $dbuser@'%' IDENTIFIED BY '$dbpass';",
                "GRANT ALL PRIVILEGES ON $dbname.* TO $dbuser@'%' IDENTIFIED BY '$dbpass';",
            );
            foreach ($queries as $query) {
                $db->query($query);
            }
        } catch (\Exception $e) {
            $this->printLine("WARN: Database not created", 0, true);
        }

        $details = array(
            "server" => $credentials['ip'],
            "db" => $dbname,
            "user" => $dbuser,
            "password" => $dbpass
        );

        $this->createConfigTable($details);
        return $details;

    }

    protected function formatArgument($name) {
        return preg_replace('/[^a-z]/','', strtolower($name));
    }

}

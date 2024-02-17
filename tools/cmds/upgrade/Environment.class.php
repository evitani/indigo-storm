<?php

namespace Tools\Upgrade;

use Tools\Tool;

class Environment extends Tool {

    public function run() {
        $envName = strtolower($this->args['target']);
        $workingDir = 'config/' . $envName;

        if (file_exists($workingDir . '.yaml')) {
            $this->printLine('Environment already upgraded.', 0, true);
            exit;
        } elseif (!file_exists($workingDir) || !is_dir($workingDir)) {
            $this->printLine('Environment can\'t be found', 0, true);
            exit;
        }

        $this->printLine('Loading configs...');
        $sharedEnvironmentConfig = array();
        if (file_exists($workingDir . '/shared.config.php')) {
            require_once $workingDir . '/shared.config.php';
        }
        $localEnvironmentConfig = array();
        if (file_exists($workingDir . '/local.config.php')) {
            $environmentConfig = array();
            require_once $workingDir . '/local.config.php';
            $localEnvironmentConfig = $environmentConfig;
        }
        $prereleaseEnvironmentConfig = array();
        if (file_exists($workingDir . '/prerelease.config.php')) {
            $environmentConfig = array();
            require_once $workingDir . '/prerelease.config.php';
            $prereleaseEnvironmentConfig = $environmentConfig;
        }
        $releaseEnvironmentConfig = array();
        if (file_exists($workingDir . '/release.config.php')) {
            $environmentConfig = array();
            require_once $workingDir . '/release.config.php';
            $releaseEnvironmentConfig = $environmentConfig;
        }

        $newConfig = $sharedEnvironmentConfig;

        if (!array_key_exists('name', $newConfig)) {
            $newConfig['name'] = $envName;
        }

        if (!array_key_exists('security', $newConfig)) {
            $newConfig['security'] = array();
        }

        if (!array_key_exists('forceSSL', $newConfig['security'])) {
            $newConfig['security']['forceSSL'] = false;
        }

        foreach ($newConfig as $key => $value) {
            if ($value === null || $value === array()) {
                unset($newConfig[$key]);
            }
        }

        $newConfig['tiers'] = array();

        if ($localEnvironmentConfig !== array()) {
            $newConfig['tiers']['local'] = $this->_processTier($newConfig, $localEnvironmentConfig);
        }

        if ($prereleaseEnvironmentConfig !== array()) {
            $newConfig['tiers']['prerelease'] = $this->_processTier($newConfig, $prereleaseEnvironmentConfig);
        }

        if ($releaseEnvironmentConfig !== array()) {
            $newConfig['tiers']['release'] = $this->_processTier($newConfig, $releaseEnvironmentConfig);
        }

        if (array_key_exists('globalSalt', $newConfig['security'])) {
            unset($newConfig['security']['globalSalt']);
        }
        if (array_key_exists('url', $newConfig)) {
            unset($newConfig['url']);
        }

        if (file_exists('config/domains.php')) {
            $this->printLine('Searching domain maps...');
            $domains = array();
            require_once 'config/domains.php';
            foreach ($domains as $domain => $map) {
                if ($map[0] === $envName && array_key_exists($map[1], $newConfig['tiers'])) {

                    if (!array_key_exists('mappings', $newConfig['tiers'][$map[1]])){
                        $newConfig['tiers'][$map[1]]['mappings'] = array();
                    }
                    if (!in_array($domain, $newConfig['tiers'][$map[1]]['mappings'])){
                        array_push($newConfig['tiers'][$map[1]]['mappings'], $domain);
                    }

                }
            }
        }

        $this->printLine('Saving yaml file');
        $envName = str_replace(' ', '', $envName);
        yaml_emit_file('config/' . $envName . '.yaml', $newConfig);

        $this->printLine('Removing old files');
        foreach(scandir($workingDir) as $file) {
            unlink($workingDir . '/' . $file);
        }
        rmdir($workingDir);

        $this->printLine('Checking for new global file');
        if (!file_exists('config/global.yaml')) {
            yaml_emit_file('config/global.yaml', array(
                'devmode' => true,
                'lite' => false
            ));
        }

        $this->printLine('Checking for other old-style environments');
        $last = true;
        foreach (scandir('config') as $subfile) {
            if (is_dir('config/' . $subfile)) {
                $last = false;
                break;
            }
        }
        if ($last) {
            $this->printLine('Removing old global environment files');
            foreach(scandir('config') as $subfile){
                if (substr($subfile, -4) === '.php') {
                    unlink('config/' . $subfile);
                }
            }
        }

        $this->printLine('Environment upgraded!', 0, true);


    }

    private function _processTier($global, $tier) {

        if (!array_key_exists('security', $tier)) {
            $tier['security'] = array();
        }

        foreach ($tier as $key => $value) {
            if (array_key_exists($key, $global) && $global[$key] === $value && $key !== 'url') {
                unset($tier[$key]);
            }
        }

        if (array_key_exists('globalSalt', $global['security'])) {
            $tier['security']['globalSalt'] = $global['security']['globalSalt'];
        } else {
            $tier['security']['globalSalt'] = sha1(uniqid(json_encode($global), true));
        }

        if (!array_key_exists('url', $tier) && array_key_exists('url', $global)) {
            $tier['url'] = $global['url'];
        }

        if (array_key_exists('name', $tier)) {
            unset($tier['name']);
        }

        return $tier;

    }

}

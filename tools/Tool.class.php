<?php

namespace Tools;

class Tool {

    protected $quiet = false;
    protected $verbose = false;

    protected $argMap = array();
    protected $args = array();

    protected $flagMap = array();
    protected $flags;

    public function getFlags() {
        return $this->flagMap;
    }

    public function importFlags($flags) {
        $this->flags = $flags;
        $this->quiet = array_key_exists('quiet', $this->flags);
        $this->verbose = array_key_exists('verbose', $this->flags);
    }

    public function importArgs($args) {

        if (array_keys($args) === range(0, count($args) - 1)) {
            $i = 0;
            foreach ($this->argMap as $argName => $required) {
                if (count($args) > $i) {
                    $this->args[$argName] = $args[$i];
                } elseif ($required && !$this->quiet) {
                    $this->promptForInput($argName, true);
                } elseif ($required) {
                    $this->printLine("Required argument $argName missing");
                    exit;
                }
                $i++;
            }
        } else {
            $this->args = $args;
        }

    }

    public function run(){

    }

    protected function printLine($line, $depth = 0, $important = false) {

        if ($important === true || $this->verbose) {
            $pad = "";
            for ($i = 0; $i < $depth; $i++) {
                $pad .= " ";
            }
            echo $line . $pad . PHP_EOL;
        }

    }

    protected function printDialog($header, $body) {

        $width = 62;
        $top = "-";
        $side = "|";
        $padding = 1;

        $lineLength = $width - ($padding * 2);
        $header = strtoupper($header);

        $header = $this->splitContent($lineLength, $header);
        $body = $this->splitContent($lineLength, $body);

        $delineate = "";
        for ($i = 0; $i < $width; $i++) {
            $delineate .= $top;
        }

        $pad = "";
        for ($i = 0; $i < $padding; $i++) {
            $pad .= " ";
        }

        echo $delineate . PHP_EOL;
        foreach ($header as $headerLine) {
            $headerPad = "";
            for ($i = 0; $i < ($width - (strlen($headerLine) + 2 )); $i++) {
                $headerPad .= " ";
            }
            if (intval(strlen($headerPad) / 2) * 2 === strlen($headerPad)) {
                $headerPad = array(
                    substr($headerPad, 0,strlen($headerPad) / 2),
                    substr($headerPad, 0,strlen($headerPad) / 2)
                );
            } else {
                $headerPad = array(
                    substr($headerPad, 0, intval(strlen($headerPad) / 2)),
                    substr($headerPad, 0,intval(strlen($headerPad) / 2) + 1),
                );
            }
            echo $side . $headerPad[0] . $headerLine . $headerPad[1] . $side . PHP_EOL;
        }
        echo $side;
        for ($i = 0; $i < ($width - (($padding * 2))); $i++) {
            echo " ";
        }
        echo $side . PHP_EOL;
        foreach ($body as $bodyLine) {
            $rightPad = "";
            for ($i = 0; $i < $width - (2 + ($padding) + strlen($bodyLine)); $i++) {
                $rightPad .= " ";
            }
            echo $side . $pad . $bodyLine . $rightPad . $side . PHP_EOL;
        }
        echo $delineate . PHP_EOL;

    }

    private function splitContent($length, $content) {
        $lines = array();

        if (strlen($content) < $length) {
            return array($content);
        } else {
            $dumbTrim = substr($content, 0, $length);
            if (substr($dumbTrim, -1, 1) === " ") {
                $dumbTrim = substr($content, 0, $length - 1);
            }
            $revDumbTrim = strrev($dumbTrim);
            $split = strlen($dumbTrim) - strpos($revDumbTrim, ' ');
            array_push($lines, substr($content, 0, $split - 1));
            $content = substr($content, $split);
            return array_merge($lines, $this->splitContent($length, $content));
        }

    }

    protected function promptForInput($prompt, $required = false, $boolean = false) {

        $storedPrompt = $prompt;

        if ($this->quiet) {
            if ($required) {
                $this->printLine("Required value not provided, cannot prompt", 0, true);
                exit;
            } else {
                return $boolean ? false : null;
            }
        }

        if ($boolean) $prompt .= " (y/N)";
        if (!$required) $prompt .= " [optional]";
        $prompt .= ": ";

        $response = trim(readline($prompt));

        if ($boolean) {
            $response = strtolower($response);
            return in_array($response, array(
                'y',
                'yes',
                '1',
                'true'
            ));
        } elseif ($required && $response == '') {
            return $this->promptForInput($storedPrompt, true, $boolean);
        } else if ($response !== '') {
            return $response;
        } else {
            return null;
        }

    }

}

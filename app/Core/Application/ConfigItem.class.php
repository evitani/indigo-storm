<?php

namespace Core\Application;

class ConfigItem {

    protected $data = array();

    function __construct($data) {

        if (!is_array($data)) {
            $data = array();
        }

        foreach ($data as $datum => $value) {
            if (property_exists($this, $datum)) {
                $this->$datum = $value;
                unset($data[$datum]);
            } else {
                $this->data[$datum] = $value;
            }
        }

    }

    function __call($name, $args) {
        $type = substr(strtolower($name), 0, 3);
        switch ($type) {
            case 'get':

                $property = substr(strtolower($name), 3, 1) . substr($name, 4);
                if (property_exists($this, $property)) {
                    return $this->$property;
                } elseif (array_key_exists($property, $this->data)) {
                    return $this->data[$property];
                } else {
                    return null;
                }
                break;

            default:
                return null;
                break;
        }
    }

}

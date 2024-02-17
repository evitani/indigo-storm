<?php

namespace Core\Payloads;

class Payload {

    protected array $_other;

    public function parse($body): void {
        $entries = $this->document();

        foreach ($entries as $key => $config) {
            if (array_key_exists($key, $body) && !is_null($body[$key])) {
                try{
                    $this->$key = $body[$key];
                } catch (\TypeError $e) {
                    throw new \Exception(
                        'Payload field ' . $key .
                        ' expects ' . $config['type'] . ', got ' . gettype($body[$key])
                        , 500);
                }
                unset($body[$key]);
            } elseif ($config['required']) {
                throw new \Exception('Required field missing from payload ' . get_class($this), 500);
            }
        }

        foreach ($body as $key => $value) {
            $this->_other[$key] = $value;
        }
    }

    public function document(): array {
        $properties = array();
        foreach (get_class_vars(get_class($this)) as $key => $value) {
            if (substr($key, 0, 1) !== '_') {
                if (isset($this->$key)) {
                    $properties[$key] = array(
                        'required' => false,
                        'type' => gettype($this->$key)
                    );
                } else {

                    try {
                        $this->$key = null;
                        $type = 'null';
                    } catch (\TypeError $e) {
                        $types = array();
                        preg_match("/must be ([a-zA-Z]+),/i", $e->getMessage(), $types);
                        if (count($types) === 2) {
                            $type = $types[1];
                        } else {
                            $type = 'undefined';
                        }
                    }
                    $properties[$key] = array(
                        'required' => true,
                        'type' => $type
                    );
                }
            }
        }

        return $properties;
    }

    function __construct(?array $parseBody = null) {
        if (!is_null($parseBody)) {
            $this->parse($parseBody);
        }
    }

    function __call($name, $args) {

        $controlString = strtolower(substr($name, 0, 3));
        $remainder = substr($name, 3);
        $remainderLc = strtolower(substr($name, 3, 1)) . substr($name, 4);

        switch ($controlString){
            case 'get':
                if (isset($this->$remainderLc)) {
                    return $this->$remainderLc;
                } elseif (isset($this->$remainder)) {
                    return $this->$remainder;
                } elseif (isset($this->_other) && is_array($this->_other)) {
                    if (array_key_exists($remainderLc, $this->_other)) {
                        islog(LOG_WARNING,
                              "Property $remainderLc used but not be defined in payload " . get_class($this)
                        );
                        return $this->_other[$remainderLc];
                    } elseif (array_key_exists($remainder, $this->_other)){
                        islog(LOG_WARNING,
                              "Property $remainder used but not be defined in payload " . get_class($this)
                        );
                        return $this->$remainder;
                    }
                }
                break;
            case 'set':
                if (property_exists($this, $remainderLc)) {
                    $this->$remainderLc = $args[0];
                }
                break;
        }

        return null;

    }

    public function asArray(bool $strict = true): array {
        $array = [];

        foreach (get_class_vars(get_class($this)) as $key => $value) {
            if (substr($key, 0, 1) !== '_' && isset($this->$key)) {
                $array[$key] = $this->$key;
            } elseif($key === '_other' && isset($this->_other) && !$strict) {
                foreach ($this->_other as $okey => $ovalue) {
                    $array[$okey] = $ovalue;
                }
            }
        }

        return $array;
    }

    public function asXML(bool $strict = true) {
        $objName = explode('\\', get_class($this));
        $objName = array_pop($objName);

        $object = ['name' => $objName, 'content' => [], 'item' => true];

        foreach (get_class_vars(get_class($this)) as $key => $value) {
            if (substr($key, 0, 1) !== '_' && isset($this->$key)) {
                $object['content'][] = [
                    'name' => $key,
                    'content' => $this->$key,
                    'item' => true,
                    'type' => $this->_xml_gettype($this->$key)
                ];
            } elseif($key === '_other' && isset($this->_other) && !$strict) {
                foreach ($this->_other as $okey => $ovalue) {
                    $object['content'][] = ['name' => $okey, 'content' => $ovalue, 'item' => true];
                }
            }
        }

        return $this->_parseXML($object);
    }

    private function _parseXML($object, $depth = 0) {
        $containsParent = array_key_exists('name', $object) && !is_null($object['name']);

        if (array_key_exists('type', $object) && !is_null($object['type'])) {
            $typeString = " type=\"{$object['type']}\"";
        } else {
            $typeString = '';
        }
        if (array_key_exists('generic', $object)) {
            if ($object['name'] === '') {
                $useName = $object['generic'];
            } else {
                $useName = $object['generic'] . " name=\"" . $object['name'] . "\"";
            }
        } else {
            $useName = $object['name'];
        }

        if ($containsParent) {
            $xml = $this->_xml_depth($depth) . "<$useName$typeString>" . PHP_EOL;
        } else {
            $xml = '';
        }

        $content = $object['content'];
        if (is_array($content) && count($content) > 0 && is_array($content[0]) && array_key_exists('item', $content[0])) {
            foreach ($content as $contentItem) {
                $xml .= $this->_parseXML($contentItem, $depth + 1) . PHP_EOL;
            }
        } else {
            $xml .= $this->_xml_depth($depth + 1) . $this->_xml_content($content, $depth) . PHP_EOL;
        }
        if ($containsParent){
            $xml .= $this->_xml_depth($depth) . "</" . explode(' ', $useName)[0] .">" . PHP_EOL;
        }
        return $xml;
    }

    private function _xml_depth($depth): string {
        $padding = '  ';
        return str_repeat($padding, $depth + 1);
    }

    private function _xml_content($content, $depth): string {
        if (is_bool($content)) {
            switch ($content) {
                case true:
                    return 'true';
                case false:
                default:
                    return 'false';
            }
        }
        if (is_array($content)) {
            if ($this->_isAssoc($content)) {
                $contentItems = ['name' => null, 'content' => []];
                foreach ($content as $key => $item) {
                    $contentItems['content'][] = [
                        'generic' => 'ArrayItem',
                        'name' => $key,
                        'content' => $item,
                        'item' => true,
                        'type' => $this->_xml_gettype($item)
                    ];
                }
                $contentItems = $this->_parseXML($contentItems, $depth + 1);
            } else {

                $contentItems = ['name' => null, 'content' => []];
                foreach ($content as $item) {
                    $contentItems['content'][] = [
                        'name' => '',
                        'generic' => 'ArrayItem',
                        'content' => $item,
                        'item' => true,
                        'type' => $this->_xml_gettype($item)
                    ];
                }
                $contentItems = $this->_parseXML($contentItems, $depth + 1);
            }
            $content = $contentItems;
        }
        return $content;
    }

    private function _xml_gettype($item): ?string {
        $type = gettype($item);
        $replacements = [
            'float' => 'decimal',
            'double' => 'decimal',
            'int' => 'integer',
            'bool' => 'boolean'
        ];
        $ignore = [
            'object',
            'array'
        ];
        if (array_key_exists($type, $replacements)) {
            $type = $replacements[$type];
        }
        if (in_array($type, $ignore)){
            return null;
        } else {
            return $type;
        }
    }

    function _isAssoc(array $arr) : bool {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

}

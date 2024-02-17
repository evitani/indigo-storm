<?php

namespace Core\Routing;

class RouteMethod {

    private $type = HTTP_METHOD_GET;
    private $returnType = RETURN_JSON;
    private $argMap = array();

    private $authentication = false;
    private $accessControl = false;

    function __construct($type, $args, $return, $authentication = false, $accessControl = false) {

        if ($this->_checkType($type)) {
            $this->type = $type;
        } else {
            throw new \Exception("Invalid method", 500);
        }

        $returnType = $this->_extractReturn($return);
        if ($this->_checkReturnType($returnType)) {
            $this->returnType = $returnType;
        }

        foreach ($args as $argSet) {
            if (is_null($argSet)) {
                $this->argMap[0] = true;
            } elseif (is_string($argSet)) {
                $this->argMap[1] = $argSet;
            } elseif (is_array($argSet)) {
                $this->argMap[count($argSet)] = $argSet;
            }
        }

        $this->authentication = $authentication;
        $this->accessControl = $accessControl;
    }

    public function handle($args) {
        if (array_key_exists(count($args), $this->argMap)){
            return array(
                'returnType' => $this->returnType,
                'args'       => $this->argMap[count($args)],
                'authentication' => $this->authentication,
                'accessControl' => $this->accessControl,
            );
        } else {
            return false;
        }
    }

    private function _extractReturn($return) {
        if (is_string($return)){
            return $return;
        } elseif (is_array($return) && array_key_exists($this->type, $return)) {
            return $return[$this->type];
        } else {
            return RETURN_JSON;
        }
    }

    private function _checkType($type) {
        $type = strtolower($type);
        $allowed = array(HTTP_METHOD_GET, HTTP_METHOD_POST, HTTP_METHOD_PUT, HTTP_METHOD_DELETE);
        return in_array($type, $allowed);
    }

    private function _checkReturnType($returnType) {
        $returnType = strtolower($returnType);
        $allowed = array(RETURN_JSON, RETURN_FILE);
        return in_array($returnType, $allowed);
    }
}

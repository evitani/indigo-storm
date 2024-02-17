<?php

namespace Core\Controllers;


use Core\Routing\Request;
use Core\Routing\Response;

/**
 * Basic Controller template. This defines the possible options for a controller, so if an extension does not include it
 * the exception method in this controller will catch any attempts to use it.
 *
 * @package Core\Controllers
 */
class BaseController{

    public function handleGet(Request $request, Response $response, array $args){
        throw new \Exception('Could not handle request using this method (GET)', 405);
    }

    public function handlePost(Request $request, Response $response, array $args){
        throw new \Exception('Could not handle request using this method (POST)', 405);
    }

    public function handlePut(Request $request, Response $response, array $args){
        throw new \Exception('Could not handle request using this method (PUT)', 405);
    }

    public function handleDelete(Request $request, Response $response, array $args){
        throw new \Exception('Could not handle request using this method (DELETE)', 405);
    }

    /**
     * Check an array payload matches a list of expected keys. Note this only checks if a key is present and only at the
     * top level of the payload, it does not check content or complex payload structures
     * @param array $payload The payload (or any key/value array)
     * @param array $requiredKeys An array of the required keys
     * @param bool $strict When true, fields in the payload that aren't required will cause a failure (default false)
     * @return bool Whether the payload is valid or not
     */
    protected function _validatePayload(array $payload, array $requiredKeys, bool $strict = false) {
        $payloadKeys = array_keys($payload);

        foreach ($requiredKeys as $requiredKey) {
            if (!in_array($requiredKey, $payloadKeys)) {
                return false;
            }
        }

        if ($strict) {
            foreach($payloadKeys as $payloadKey) {
                if (!in_array($payloadKey, $requiredKeys)) {
                    return false;
                }
            }
        }

        return true;

    }

}

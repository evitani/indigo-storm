<?php

namespace Core\Routing;

use Core\Payloads\File;

class Response {

    private $headers = array();
    private $content;
    private $returnType;

    function __construct($handler = null) {
        if (!is_null($handler) && is_array($handler) && array_key_exists('returnType', $handler)) {
            $this->returnType = $handler['returnType'];
        }
    }

    public function withHeader($header, $value) {
        if (array_key_exists($header, $this->headers)) {
            if (!in_array($value, $this->headers[$header])) {
                array_push($this->headers[$header], $value);
            }
        } else {
            $this->headers[$header] = array($value);
        }
        return $this;
    }

    public function withJson($data) {
        $this->withHeader('Content-Type', 'application/json');
        if (gettype($data) === 'object' && method_exists($data, 'asArray')) {
            $this->content = json_encode($data->asArray(true));
        } else {
            $this->content = json_encode($data);
        }
        return $this;
    }

    public function withXML($data) {
        $this->withHeader('Content-Type', 'application/xml');
        if (gettype($data) === 'object' && method_exists($data, 'asXML')) {
            $this->content = $data->asXML(true);
        } else {
            $this->content = $data;
        }
        return $this;
    }

    public function withFile (File $file) {
        $this->content = $file->getContent();
        $this->withHeader('Content-Type', $file->getMime());
        $this->withHeader('Content-Disposition',
                          'filename="' . str_replace('"', '\"', $file->getName()) . '"'
        );
        return $this;
    }

    public function withDownload (File $file, $extension = null) {
        if (!is_null($extension) && substr($extension, 0, 1) !== '.') {
            $extension = '.' . $extension;
        }
        $this->content = $file->getContent();
        $this->withHeader('Content-Type', $file->getMime());
        $this->withHeader('Content-Disposition',
                          'attachment; filename="' .
                          str_replace('"', '\"', $file->getName()) . $extension . '"'
        );
        return $this;
    }

    public function withContent($content) {

        if (is_null($this->content) && !is_null($content)){

            switch (strtolower($this->returnType)) {
                case RETURN_FILE:
                    return $this->withFile($content);
                case RETURN_XML:
                    return $this->withXML($content);
                case RETURN_JSON:
                default:
                    return $this->withJson($content);
            }

        } else {

            return $this;

        }

    }

    public function serve() {

        foreach ($this->headers as $header => $val) {
            if (is_array($val) && count($val) > 1) {
                $val = implode(",", $val);
            } elseif (is_array($val)) {
                $val = $val[0];
            }
            header("$header: $val");
        }

        echo $this->content;
    }

}

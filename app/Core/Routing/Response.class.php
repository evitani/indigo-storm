<?php

namespace Core\Routing;

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
        $this->content = json_encode($data);
        return $this;
    }

    public function withFile($base64, $mime) {
        $this->content = base64_decode($base64);
        $this->withHeader('Content-Type', $mime);
        return $this;
    }

    public function withContent($content) {

        if (is_null($this->content) && !is_null($content)){

            if(strtolower($this->returnType) === RETURN_FILE){
                return $this->withFile($content['content'], $content['mime']);
            }else{
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

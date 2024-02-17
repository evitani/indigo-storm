<?php

namespace Core\Payloads;

class File extends Payload {

    protected string $content;
    protected string $mime;

    protected string $name = '';
    protected int $size = 0;

    public function getSize($units = 'B') {
        $units = trim(strtoupper($units));

        $allowedDecimal = ['B', 'KB', 'MB', 'GB'];
        if (in_array($units, $allowedDecimal)) {
            $powers = array_flip($allowedDecimal);
            return $this->size / (10 ** ($powers[$units] * 3));
        }

        $allowedBinary = ['KIB', 'MIB', 'GIB'];
        if (in_array($units, $allowedBinary)) {
            $powers = array_flip($allowedBinary);
            return $this->size / (1024 ** ($powers[$units] + 1));
        }

        throw new \Exception('Invalid unit definition ' . $units, 500);
    }

    public function getBase64() {
        return base64_encode($this->content);
    }

    public function parse($body, $base64 = false){

        if (array_key_exists('base64', $body) && is_bool($body['base64'])) {
            $base64 = $body['base64'];
            unset($body['base64']);
        }

        if ($base64 && array_key_exists('content', $body)) {
            $body['content'] = base64_decode($body['content']);
        }

        parent::parse($body);

        if ($this->name === '') {
            $this->name = sha1(uniqid(substr($this->content, 0, min(strlen($this->content), 128))));
        }
        if ($this->size === 0) {
            $this->size = strlen($this->content);
        }
    }

}

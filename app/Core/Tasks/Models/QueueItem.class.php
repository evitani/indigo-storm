<?php

namespace Core\Tasks\Models;

class QueueItem {

    protected array $config = array(
        'url' => null,
        'payload' => null,
        'headers' => null,
    );

    function __construct($config) {
        foreach ($config as $ckey => $cval) {
            $this->config[$ckey] = $cval;
        }
    }

    public function queue(int $delay = 0): bool {

        if ($delay > 0) {
            islog(LOG_INFO, 'This environment doesn\'t support task delays, queued instantly instead.');
        }

        $task = new QueuedTask();
        $task->setName(sha1(uniqid('', true)));
        $task->setMetadata($this->config);
        $task->setMetadata('state', 'WAITING');
        $task->setMetadata('queued', time());
        try{
            $task->persist();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function _supportsBody(): bool {
        $supportsBody = array(HTTP_METHOD_POST, HTTP_METHOD_PUT);
        return in_array(strtolower($this->config['method']), $supportsBody);
    }

}

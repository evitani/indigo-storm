<?php

namespace Core\Log;

class Logger {

    public function log(int $priority, string $message) {
        syslog($priority, $message);
    }

}

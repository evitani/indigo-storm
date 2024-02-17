<?php
$logger = null;

/**
 * Generate a system log message
 * @param $priority int A combination of the facility and the level
 * @param $message string The message to send to the log
 */
function islog(int $priority, string $message){
    global $logger;

    if (is_null($logger)) {
        if (targetOverrides('Log\Logger')) {
            $logger = new Target\Log\Logger();
        } else {
            $logger = new Core\Log\Logger();
        }
    }

    $logger->log($priority, $message);

}

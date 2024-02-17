<?php

$gaeLogger = null;

/**
 * Generate a system log message
 * @param $priority int A combination of the facility and the level
 * @param $message string The message to send to the log
 */
function islog($priority, $message){

    if(getenv('GAE_APPLICATION') !== false && PHP_MAJOR_VERSION === 7){
        //Running in App Engine 7, so prepare an App Engine logger
        global $gaeLogger;

        if(is_null($gaeLogger)){
            $gaeLogger = Google\Cloud\Logging\LoggingClient::psrBatchLogger('app');
        }

        switch ($priority){
            case LOG_EMERG:
                $gaeLogger->emergency($message);
                break;
            case LOG_ALERT:
                $gaeLogger->alert($message);
                break;
            case LOG_CRIT:
                $gaeLogger->critical($message);
                break;
            case LOG_ERR:
                $gaeLogger->error($message);
                break;
            case LOG_WARNING:
                $gaeLogger->warning($message);
                break;
            case LOG_NOTICE:
                $gaeLogger->notice($message);
                break;
            case LOG_INFO:
            default:
                $gaeLogger->info($message);
                break;
            case LOG_DEBUG:
                $gaeLogger->debug($message);
                break;
        }
    }else{
        //Not in App Engine 7, default to syslog
        syslog($priority, $message);
    }

}

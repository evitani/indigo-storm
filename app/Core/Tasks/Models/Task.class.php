<?php

namespace Core\Tasks\Models;

use Services\User\AsyncAuthInterface;

class Task {

    private array $allowedOptions = array(
        'method',
        'headers'
    );

    private array $defaultOptions = array(
        'method' => HTTP_METHOD_GET,
    );

    private array $config = array(
        'url' => null,
        'payload' => null,
        'headers' => null,
    );

    public function __construct($service, $url, $data = null, $options = null) {
        $this->config['url'] = $this->buildUrl($service, $url);
        if (is_array($data) && count($data) > 0){
            $this->config['payload'] = $data;
        }
        $this->parseOptions($options);
    }

    /**
     * Add the task to the queue to run as soon as resource allows. This may be instant, or there may be a delay,
     * depending on both the environment and the number of other tasks enqueued ahead of it.
     * @param int $delay Minimum number of seconds to delay prior to task running (not always observed)
     * @return bool Whether the task was enqueued successfully (does not equate to the task having run)
     */
    public function run(int $delay = 0): bool {

        $this->setAuth('task');

        $queueItem = $this->_getQueueItem();
        return $queueItem->queue($delay);

    }

    private function _getQueueItem() {
        if (
            targetOverrides('Tasks\Models\QueueItem')
        ) {
            return new \Target\Tasks\Models\QueueItem($this->config);
        } else {
            return new \Core\Tasks\Models\QueueItem($this->config);
        }
    }

    private function setAuth($type = 'task') {
        global $indigoStorm;

        $tree = $indigoStorm->getRouter()->getRequest()->getTree();
        $key = $indigoStorm->getRouter()->getRequest()->getKey();

        if (class_exists('Services\User\AsyncAuthInterface') && $tree->getMetadata('user')) {
            $asyncAuth = new AsyncAuthInterface();
            switch (strtolower($type)) {
                case 'cron' :
                    $token = $asyncAuth->getCronToken();
                    break;
                case 'task' :
                default:
                    $token = $asyncAuth->getTaskToken();
                    break;
            }
            $this->config['headers']['is-session'] = $token;
        }

        if (!is_null($key)) {
            $this->config['headers']['is-api-key'] = $key->getName();
        }

    }

    /**
     * Schedule the task to run repeatedly based on a cron expression. Depending on the environment, tasks may be queued
     * at run-time, so their exact start should not be presumed to match the schedule exactly.
     * @param string $cronExpression The regularity of the task
     * @return bool Whether the task was scheduled successfully
     */
    public function schedule(string $cronExpression): bool {
        $this->setAuth('cron');
        $cronItem = $this->_getCronItem();
        return $cronItem->schedule($cronExpression);
    }

    private function _getCronItem() {
        if (
            targetOverrides('Task\Models\CronItem')
        ) {
            return new \Target\Tasks\Models\CronItem($this->config);
        } else {
            return new \Core\Tasks\Models\CronItem($this->config);
        }
    }

    private function parseOptions($options) {
        global $indigoStorm;

        if (!is_array($options)) {
            $options = array();
        }

        foreach ($options as $optKey => $optValue) {
            if (!in_array($optKey, $this->allowedOptions)) {
                unset($options[$optKey]);
            }
        }

        $options = $this->specialRecursiveMerge($this->defaultOptions, $options);

        foreach ($options as $optKey => $optValue) {
            $this->config[$optKey] = $optValue;
        }

        // Add default headers to keep track of env information and triggering task
        if (!is_array($this->config['headers'])) {
            $this->config['headers'] = array();
        }

        $envDetails = array(
            $indigoStorm->getConfig('env'),
            $indigoStorm->getConfig('tier'),
        );

        $this->config['headers']['is-triggered-by'] = $indigoStorm->getRouter()->getRequest()->getTree()->getName();
        $this->config['headers']['is-identity'] = $envDetails[0] . '-' . $envDetails[1];
        $this->config['headers']['Content-Type'] = "application/json";
    }

    private function buildUrl($service, $url): string {
        global $indigoStorm;

        $service = preg_replace('/([A-Z])/', ' ${1}', $service);
        $service = trim($service);
        $service = str_replace(' ', '-', $service);
        $service = strtolower($service);

        $serviceUrl = $indigoStorm->getConfig('url');
        $serviceUrl = str_replace('_SERVICE_', $service, $serviceUrl);

        if (substr($url, -1, 1) !== '/') {
            $serviceUrl .= '/';
        }

        return $serviceUrl . $url;

    }

    private function specialRecursiveMerge($array1, $array2): array {

        foreach($array1 as $arrKey => $arrVal) {

            if (!array_key_exists($arrKey, $array2)) {
                $array2[$arrKey] = $arrVal;
            } elseif (is_array($arrVal) && $this->isAssocArray($arrVal)) {
                $array2[$arrKey] = $this->specialRecursiveMerge($arrVal, $array2[$arrKey]);
            } elseif (is_array($arrVal)) {
                $array2[$arrKey] = array_merge($arrVal, $array2[$arrKey]);
            }

            return $array2;

        }

        return [];

    }

    private function isAssocArray(array $arr): bool {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

}

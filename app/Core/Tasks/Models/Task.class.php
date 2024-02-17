<?php

namespace Core\Tasks\Models;

use Google\ApiCore\ApiException;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\HttpRequest;
use Google\Protobuf\Timestamp;
use Services\User\AsyncAuthInterface;

class Task {

    private $allowedOptions = array(
        'method',
        'headers'
    );

    private $defaultOptions = array(
        'method' => HTTP_METHOD_GET,
    );

    private $config = array(
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

    public function run($delay = 0) {

        $this->setAuth('task');

        $isGAE = getenv('GAE_APPLICATION') !== false;

        if ($isGAE) {
            // Inside App Engine, send the task to a queue
            if (!$this->_runGAE($delay)) {
                // Cloud Task couldn't be created, store it instead and warn
                islog(LOG_WARNING, "Task set failed, adding to internal queue instead");
                return $this->_runQueue();
            } else {
                return true;
            }
        } else {
            // Not in App Engine, queue the task internally for a cron to run
            islog(LOG_INFO, "Tasks won't run in this environment without a cron, queued instead");
            return $this->_runQueue();
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

    public function schedule($cronExpression) {
        $this->setAuth('cron');

        $task = new CronTask();
        $task->setName(sha1(uniqid('', true)));
        $task->setMetadata($this->config);
        $task->setMetadata('runRule', $cronExpression);

        try{
            $task->persist();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function _runGAE($delay = 0) {
        global $indigoStorm;

        try{
            $gae = $indigoStorm->getConfig('gae');
        } catch (\Exception $e) {
            islog(LOG_WARNING, "Tasks cannot be enqueued without App Engine environment details");
            return false;
        }

        $client = new CloudTasksClient();

        $queueName = $client->queueName($gae->getProject(), $gae->getLocation(), $gae->getQueue());

        $request = new HttpRequest();
        $request->setUrl($this->config['url']);
        $request->setHttpMethod($this->getGaeMethod());
        $request->setHeaders($this->config['headers']);

        if (is_array($this->config['payload']) && count($this->config['payload']) > 0 && $this->supportsBody()) {
            $request->setBody(json_encode($this->config['payload']));
        }

        if ($delay > 0) {
            $runTime = time() + $delay;
            $task = new \Google\Cloud\Tasks\V2\Task(['schedule_time' => new Timestamp(['seconds' => $runTime])]);
        } else {
            $task = new \Google\Cloud\Tasks\V2\Task();
        }

        $task->setHttpRequest($request);

        try {
            $client->createTask($queueName, $task);
            return true;
        } catch (ApiException $e) {
            islog(LOG_WARNING, $e->getMessage());
            return false;
        }

    }

    private function _runQueue() {
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

    private function buildUrl($service, $url){
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

    private function specialRecursiveMerge($array1, $array2){

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

    }

    private function isAssocArray(array $arr) {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function supportsBody() {
        $supportsBody = array(HTTP_METHOD_POST, HTTP_METHOD_PUT);
        return in_array(strtolower($this->config['method']), $supportsBody);
    }

    private function getGaeMethod() {
        switch (strtolower($this->config['method'])) {
            case HTTP_METHOD_POST :
                return HttpMethod::POST;
                break;
            case HTTP_METHOD_PUT :
                return HttpMethod::PUT;
                break;
            case HTTP_METHOD_DELETE :
                return HttpMethod::DELETE;
                break;
            case HTTP_METHOD_GET :
            default:
                return HttpMethod::GET;
                break;
        }
    }

}

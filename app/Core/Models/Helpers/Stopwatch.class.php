<?php

namespace Core\Models\Helpers;

class Stopwatch{
    private $startTime = null;
    private $stopTime = null;
    private $pauses = array();
    private $counter = 0;
    private $counterHash = null;
    private $state = 'ready';

    public function start(){
        if($this->state === 'ready'){
            $this->startTime = microtime(true);
            $this->state = 'running';

            return true;
        }elseif($this->state === 'paused'){
            $this->pauses[count($this->pauses) - 1]['end'] = microtime(true);
            $this->state = 'running';

            return true;
        }else{
            return false;
        }
    }

    public function pause(){
        if($this->state === 'running'){
            $this->pauses[] = array('start' => microtime(true));
            $this->state = 'paused';

            return true;
        }else{
            return false;
        }
    }

    public function resume(){
        if($this->state === 'paused'){
            return $this->start();
        }else{
            return false;
        }
    }

    public function stop(){
        if($this->state === 'running'){
            $this->stopTime = microtime(true);
            $this->state = 'stopped';

            return true;
        }elseif($this->state === 'paused'){
            $now = microtime(true);
            $this->pauses[count($this->pauses) - 1]['end'] = $now;
            $this->stopTime = $now;
            $this->state = 'stopped';

            return true;
        }else{
            return false;
        }
    }

    public function getCounter(){
        if($this->state !== 'ready'){

            if($this->counter > 0 && ($this->state === 'paused' || $this->state === 'stopped')){
                $checksum = sha1($this->startTime . $this->stopTime . json_encode($this->pauses) . $this->state);
                if($checksum === $this->counterHash){
                    return $this->counter;
                }
            }

            if(!is_null($this->stopTime)){
                $stopTime = $this->stopTime;
            }else{
                $stopTime = microtime(true);
            }

            $reduction = 0;

            if(count($this->pauses) > 0){
                foreach($this->pauses as $pause){
                    if(array_key_exists('end', $pause)){
                        $toReduce = $pause['end'] - $pause['start'];
                    }elseif($this->state === 'paused'){
                        $toReduce = microtime(true) - $pause['start'];
                    }else{
                        throw new \Exception('Stopwatch pause state impossible', 500);
                    }
                    $reduction += $toReduce;
                }
            }

            $this->counter = ($stopTime - $this->startTime) - $reduction;
            $this->counterHash = sha1($this->startTime . $this->stopTime . json_encode($this->pauses) . $this->state);

            return $this->counter;
        }else{
            return 0;
        }
    }
}

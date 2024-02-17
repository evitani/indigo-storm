<?php
namespace Core\Models;

use Services\User\UserInterface;

class ApiUser{

    private $userInterface;

    private $access;
    private $id;

    private $supported = false;

    private $hasUser = false;

    public function __construct($userToken){
        if(!class_exists('Services\User\UserInterface')){
            islog(LOG_INFO, "User service not found, user-specific access disabled");
            return;
        }else{
            $this->userInterface = new UserInterface();

            $apiResponse = $this->userInterface->getApiUser($userToken);
            $this->supported = true;

            $apiResponse = get_object_vars($apiResponse);

            if(array_key_exists('user', $apiResponse)){
                $this->id = $apiResponse['user'];
                $this->hasUser = true;
                $this->access = get_object_vars($apiResponse['access']);
            }else{
                $this->hasUser = false;
            }

        }

    }

    public function getUser(){
        return $this->id;
    }

    public function checkAccess($accessGroup = null){

        if(is_null($accessGroup)){
            return $this->hasUser;
        }

        $accessGroup = strtoupper($accessGroup);
        if(array_key_exists($accessGroup, $this->access)){
            $expires = intval($this->access[$accessGroup]);
            if($expires === -1){
                return true;
            }elseif(time() <= $expires){
                return true;
            }else{
                return false;
            }
        }
    }

}

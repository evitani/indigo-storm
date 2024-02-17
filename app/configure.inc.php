<?php

if(array_key_exists('HTTP_IS_IDENTITY', $_SERVER)){

    //Find the configuration for the system based on the incoming is-identity.
    $identityHeader = $_SERVER['HTTP_IS_IDENTITY'];
    $incomingIdentity = explode('-', strtolower($identityHeader));

}else{

    //Find the configuration based on the URL
    $link = strtolower($_SERVER['HTTP_HOST']);
    if(substr($link, 0, 3) === 'api'){

        $locale = array();
        preg_match('/api-([\w]*)./', $link, $locale);
        if(count($locale) > 1){
            define('_LOCALE_', $locale[1]);
        }else{
            define('_LOCALE_', 'default');
        }

        $link = preg_replace('/api\.|api-[\w]*./', '', $link);
    }

    include 'config/domains.php';

    if(array_key_exists($link, $domains)){
        //Configuration found by URL
        $incomingIdentity = $domains[$link];
    }else{
        //Fail as URL doesn't match anything recorded
        throw new \Exception('Target environment could not be identified', '502');
    }

}

if(is_array($incomingIdentity)){

    if(count($incomingIdentity) === 1){
        //The domain doesn't define a tier, default to release.
        $incomingIdentity[1] = 'release';
    }

    if(count($incomingIdentity) !== 2){
        //There are an invalid number of values in the array (expecting exactly 2).
        throw new \Exception('Target environment could not be identified', '502');
    }
}elseif(is_string($incomingIdentity)){
    //No tier was specified, so default to release.
    $incomingIdentity = array($incomingIdentity, 'release');
}else{
    //Fail on configuration error
    throw new \Exception('Target environment could not be identified', '502');
}

$Application->setEnvironment($incomingIdentity);

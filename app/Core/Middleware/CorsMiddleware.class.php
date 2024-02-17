<?php

namespace Core\Middleware;

use Core\Routing\Request;
use Core\Routing\Response;

class CorsMiddleware extends BaseMiddleware{

    public function handleMiddleware(Request $request, Response $response){

        global $indigoStorm;

        $allowedHeaders = array(
            'Access-Control-Allow-Origin',
            'Pragma',
            'Cache-Control',
            'If-Modified-Since',
            'Content-Type',
            'enctype',
            'Is-Api-Key',
            'Is-Session',
            'Is-Triggered-By',
            'Is-Identity',
        );

        foreach ($allowedHeaders as $allowedHeader) {
            $response = $response->withHeader('Access-Control-Allow-Headers', $allowedHeader);
        }

        if(!is_null($request->getServerParam('HTTP_ORIGIN'))){
            $incomingUrl = $request->getServerParam('HTTP_ORIGIN');
        } else {
            $incomingUrl = $request->getServerParam('HTTP_REFERER');
        }

        if ($indigoStorm->getConfig('security')->getForceSSL()) {
            $useSSL = true;
        } else {
            $useSSL = strpos($incomingUrl, 'https') !== false;
        }
        $incomingUrl = str_replace(array('http://', 'https://'), array('', ''), $incomingUrl);
        $incomingUrl = explode('/', $incomingUrl);
        $incomingUrl = $incomingUrl[0];

        $protectedIncomingUrl = $incomingUrl;

        if($useSSL){
            $protocol = 'https://';
        }else{
            $protocol = 'http://';
        }

        $host = $indigoStorm->getHost($request->getHandlingService());

        if($request->requiresAuthentication() && !is_null($request->getKey())){

            $allowedDomains = $request->getKey()->getMetadata('domains');

            if(!is_null($allowedDomains) && is_array($allowedDomains) && count($allowedDomains) > 0){

                $singleDomainFallback = $request->getKey()->getMetadata('domain');

                if(is_string($singleDomainFallback) && $singleDomainFallback !== ''){
                    array_push($allowedDomains, $singleDomainFallback);
                }

                $dealt = false;

                foreach($allowedDomains as $allowedDomain){
                    if($this->checkDomain($allowedDomain, $incomingUrl) !== false){
                        $response = $response->withHeader('Access-Control-Allow-Origin', $protocol . $this->checkDomain($allowedDomain, $incomingUrl));
                        $dealt = true;
                    }
                }

                if(!$dealt){
                    $limitedUrl = $indigoStorm->getHost($request->getHandlingService());
                    $limitedUrl = explode('/', $limitedUrl);
                    $response = $response->withHeader('Access-Control-Allow-Origin', $protocol . $limitedUrl[0]);
                }

            }else{

                if($this->checkDomain($request->getKey()->getMetadata('domain'), $incomingUrl) !== false){
                    $response = $response->withHeader('Access-Control-Allow-Origin', $protocol . $this->checkDomain($request->getKey()->getMetadata('domain'), $incomingUrl));
                }else{
                    $limitedUrl = $indigoStorm->getHost($request->getHandlingService());
                    $limitedUrl = explode('/', $limitedUrl);
                    $response = $response->withHeader('Access-Control-Allow-Origin', $protocol . $limitedUrl[0]);
                }

            }
        } elseif ($protectedIncomingUrl !== '') {
            $response = $response->withHeader('Access-Control-Allow-Origin', $protocol . $protectedIncomingUrl);
        }

        $response = $this->next($request, $response);

        return $response;

    }

    protected function checkDomain($allowedDomain, $currentDomain){

        if(substr($allowedDomain, 0, 2) === '*.'){
            $incomingUrl = explode('.', $currentDomain);
            $incomingUrl[0] = '*';
            $incomingUrl = implode('.', $incomingUrl);
        }else{
            $incomingUrl = $currentDomain;
        }

        if($incomingUrl === $allowedDomain){
            return $currentDomain;
        }else{
            return false;
        }

    }
}

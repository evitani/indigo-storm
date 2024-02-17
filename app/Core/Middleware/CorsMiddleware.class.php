<?php

namespace Core\Middleware;

class CorsMiddleware extends BaseMiddleware{
    public function __invoke($request, $response, $next){
        global $Application;

        $response = $response->withHeader('Access-Control-Allow-Headers', 'Access-Control-Allow-Origin,Pragma,Cache-Control,If-Modified-Since,Content-Type,is-api-key');

        if($request->getServerParam('HTTP_ORIGIN')){
            $incomingUrl = $request->getServerParam('HTTP_ORIGIN');
        }elseif($request->getServerParam('HTTP_REFERER')){
            $incomingUrl = $request->getServerParam('HTTP_REFERER');
        }else{
            $incomingUrl = $request->getUri()->getHost();
        }

        $incomingUsesSSL = strlen(str_replace('https', '', $incomingUrl)) < strlen($incomingUrl);

        $incomingUrl = str_replace(array('http://', 'https://'), array('', ''), $incomingUrl);
        $incomingUrl = explode('/', $incomingUrl);
        $incomingUrl = $incomingUrl[0];

        $protectedIncomingUrl = $incomingUrl;

        if($incomingUsesSSL){
            $protocol = 'https://';
        }else{
            $protocol = 'http://';
        }

        if($Application->isEndpointSecure($request->getAttribute('route')->getName(), $_SERVER['REQUEST_METHOD']) && !is_null($Application->key)){

            $allowedDomains = $Application->key->getMetadata('domains');

            if(!is_null($allowedDomains) && is_array($allowedDomains) && count($allowedDomains) > 0){

                $singleDomainFallback = $Application->key->getMetadata('domain');

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
                    $limitedUrl = str_replace(array('http://', 'https://'), array('', '',
                    ), $Application->getEnvironmentVariable('url'));
                    $limitedUrl = explode('/', $limitedUrl);
                    $response = $response->withHeader('Access-Control-Allow-Origin', $protocol . $limitedUrl[0]);
                }

            }else{

                if($this->checkDomain($Application->key->getMetadata('domain'), $incomingUrl) !== false){
                    $response = $response->withHeader('Access-Control-Allow-Origin', $protocol . $this->checkDomain($Application->key->getMetadata('domain'), $incomingUrl));
                }else{
                    $limitedUrl = str_replace(array('http://', 'https://'), array('', '',
                    ), $Application->getEnvironmentVariable('url'));
                    $limitedUrl = explode('/', $limitedUrl);
                    $response = $response->withHeader('Access-Control-Allow-Origin', $protocol . $limitedUrl[0]);
                }

            }
        }else{
            $response = $response->withHeader('Access-Control-Allow-Origin', $protocol . $protectedIncomingUrl);
        }

        $response = $next($request, $response);

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

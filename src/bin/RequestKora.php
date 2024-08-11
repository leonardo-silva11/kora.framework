<?php
namespace kora\bin;

use kora\lib\collections\Collections;
use Symfony\Component\HttpFoundation\Request;
use kora\lib\exceptions\DefaultException;

class RequestKora
{
    private AppKora $app;
    private Request $Request;
    private array $config;
    
    public function __construct(array &$config)
    {
        $this->config = &$config;

        $this->app = $config['app']['instance'];

        $this->Request = $config['http']['request']['instance'];

        $this->parseCurrentRoute()
             ->_configController()
             ->_configAction()
             ->_configUrl()
             ->_paramsRequest();

        return $this;
    }

    private function paramsRequestValidateAll($httpMethod,$params,$p)
    {
            if(
                (!in_array("*",$params['optional']) && !in_array($p,$params['optional'])) 
                &&
                (!in_array("*",$params['required']) && !in_array($p,$params['required']))
              )
            {

                throw new DefaultException("The parameter `{$p}` is not allowed from request `{$httpMethod}` for app: `{$this->app->getParamConfig('app.name')}` !",400);
            }
    }

    private function parseCurrentRoute()
    {    
 
        $nameOfApp =  $this->app->getParamConfig('app.class','protected');
        $routes = $this->app->getParamConfig('app.routes.routes','protected');
        $routeDefault = $this->app->getParamConfig("appSettings.apps.{$nameOfApp}.defaultRoute",'protected');

        if(!Collections::arrayKeyExistsInsensitive($routeDefault,$routes))
        {
            throw new DefaultException("default route {{$routeDefault}} has not been configured for the {$nameOfApp} application!",500,
                    ['info' => 'check your appsettings file','internalCode' => 75]);
        }

        $requestUri = $this->app->getParamConfig('http.requestUri');
        $requestUri = substr($requestUri,0,1) === '/' &&  mb_strlen($requestUri) > 1 ? substr($requestUri,1) : $requestUri;
        $requestUri = explode('?',$requestUri)[0];
        $routeKey = Collections::arrayKeyExistsInsensitive($requestUri,$routes) ? $requestUri : $routeDefault;

        if(!Collections::arrayKeyExistsInsensitive($routeKey,$routes))
        {
            throw new DefaultException("route {{$requestUri}} not found!",404,
                    ['info' => 'check your configuration route file','internalCode' => 77]);
        }

        $route = Collections::getElementArrayKeyInsensitive($routeKey,$routes);

        $this->config['http']['route'] = $route['element'];
        $this->config['http']['route']['routeKey'] = $routeKey;
        $this->app->parseRouteConfig($this->config);

        return $this;
    }

    private function paramsRequestValidate($httpMethod,$params,$queryParameters,$formParameters)
    {
        $ignoreParameters = $this->app->getParamConfig('http.action.ignoreParameters');

        if(!$ignoreParameters)
        {
            foreach($queryParameters as $k => $p)
            {
                $this->paramsRequestValidateAll($httpMethod,$params,$k);
                
            }
          
            foreach($formParameters as $k => $p)
            {
                $this->paramsRequestValidateAll($httpMethod,$params,$k);
            }
    
            foreach($params['required'] as $p)
            {
                if(!array_key_exists($p,$queryParameters) && !array_key_exists($p,$formParameters))
                {
                    throw new DefaultException("The parameter `{$p}` is required for request `{$httpMethod}` for app: `{$this->app->getParamConfig('app.name')}` !",400);
                }
            }
        }
    }

    private function _configAction()
    {
        $configAction = $this->app->getParamConfig('http.route.actions');
        $action = key($configAction);
        $filters = Collections::arrayKeyExistsInsensitive('filters',$configAction[$action]) ? $configAction[$action]['filters'] : [];
        $controller = $this->app->getParamConfig('http.controller.namespace');
        $ignoreParameters = Collections::arrayKeyExistsInsensitive('ignoreParameters',$configAction[$action])
                            ?
                            Collections::getElementArrayKeyInsensitive('ignoreParameters',$configAction[$action])['element']
                            : 
                            false;

        if(!method_exists($controller,$action))
        {
            throw new DefaultException("Controller {$controller} does not contains {$action} method!",404);
        }

        $this->app->setParamConfig('http.action',[
            'name' => $action,
            'filters' => $filters,
            'controller' => $controller,
            'ignoreParameters' =>  $ignoreParameters
        ],'protected');

        return $this;
    }

    private function _configUrl()
    {
        $nameOfApp = $this->app->getParamConfig('app.name');
        $action = $this->app->getParamConfig('http.action.name');

        $baseUrl = "{$this->Request->getSchemeAndHttpHost()}{$this->Request->getBaseUrl()}";

        $this->app->setParamConfig('http.request.urls',[
            'baseUrl' => $baseUrl,
            'urlApp' => "{$baseUrl}/app/{$nameOfApp}",
            'urlViews' => "{$baseUrl}/app/{$nameOfApp}/views",
            'urlView' => "{$baseUrl}/app/{$nameOfApp}/views/{$action}",
            'urlViewsSections' => "{$baseUrl}/app/{$nameOfApp}/views/sections",
            'urlPublicTemplates' => "{$baseUrl}/app/{$nameOfApp}/public/templates",
            'urlPublicAssets' => "{$baseUrl}/app/{$nameOfApp}/public/assets",
            'urlPublicAssetsCss' => "{$baseUrl}/app/{$nameOfApp}/public/assets/css",
            'urlPublicAssetsJs' => "{$baseUrl}/app/{$nameOfApp}/public/assets/js",
            'urlPublicAssetsImg' => "{$baseUrl}/app/{$nameOfApp}/public/assets/img",
            'urlPublicAssetsFonts' => "{$baseUrl}/app/{$nameOfApp}/public/assets/fonts"
        ],'protected');

        return $this;
    }

    private function _configController()
    {
        $nameOfApp = $this->app->getParamConfig('app.name');
        $pathApp = $this->app->getParamConfig('app.path');
        $controller = $this->app->getParamConfig('http.route.controller');
        $Class = "{$controller}Controller";
        $namespaceClass = "app\\$nameOfApp\\controllers\\$Class";
        $path = "{$pathApp}/controllers/$Class.php";
        $namespaceParent = "kora\bin\\ControllerKora";

        if(!file_exists($path) || !class_exists($namespaceClass))
        {
            throw new DefaultException("controller {$Class} does not exists!",404);
        }
        else if(!is_subclass_of($namespaceClass,$namespaceParent))
        {
            throw new DefaultException("controller {$Class} is not a subclass of ControllerKora!",404);
        }

        $this->app->setParamConfig('http.controller',[
            'name' => $controller,
            'class' => $Class,
            'namespace' => $namespaceClass,
            'namespaceParent' => $namespaceParent,
            'path' => $path
        ],'protected');

        return $this;
    }

    private function _paramsRequest()
    {
        $route = $this->app->getParamConfig('http.route');

        $httpMethod = mb_strtolower($this->Request->getMethod());

        if(!Collections::arrayKeyExistsInsensitive($httpMethod,$route['keyParams']))
        {
            throw new DefaultException("The HTTP method `{$httpMethod}` is not allowed!",400);
        }
                
        $formCollection = [];
        $formCollection['x-www-form-urlencoded'] = 
        $this->Request->headers->get('Content-Type') === 'application/x-www-form-urlencoded' 
        ? $this->Request->request->all()
        : [];

        $formCollection['form-data-body'] = 
        strstr($this->Request->headers->get('Content-Type'),'multipart/form-data') 
        ? array_merge($this->Request->request->all(),$this->Request->files->all())
        : [];  

        $formCollection['json'] = 
        $this->Request->headers->get('Content-Type') === 'application/json' 
        ? json_decode($this->Request->getContent(),true)
        : []; 
     
        $formParameters = array_merge($formCollection['x-www-form-urlencoded'],$formCollection['form-data-body'],$formCollection['json']);
        $queryParameters = $this->Request->query->all();

        $params = $route['keyParams'][$httpMethod];

        $this->paramsRequestValidate($httpMethod,$params,$queryParameters,$formParameters);

        $allParams = array_merge($queryParameters,$formParameters);

        $this->app->setParamConfig('http.route.params',[
            'method' => $httpMethod,
            'parameters' => $allParams
        ],'protected');
       
        return $this;
    }
}
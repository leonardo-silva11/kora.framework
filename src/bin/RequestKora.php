<?php
namespace kora\bin;

use Symfony\Component\HttpFoundation\Request;
use kora\lib\exceptions\DefaultException;

class RequestKora
{
    private AppKora $app;
    private Request $Request;
    
    public function __construct(AppKora $app, Request $Request)
    {
        $this->app = $app;
        $this->Request = $Request;
    }

    private function paramsRequestValidateAll($httpMethod,$params,$p)
    {
            if(
                (!in_array("*",$params['optional']) && !in_array($p,$params['optional'])) 
                &&
                (!in_array("*",$params['required']) && !in_array($p,$params['required']))

              )
            {

                throw new DefaultException("The parameter `{$p}` is not allowed from request `{$httpMethod}` for app: `{$this->app->getParamConfig('appName')}` !",400);
            }
    }



    private function paramsRequestValidate($httpMethod,$params,$queryParameters,$formParameters)
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
                throw new DefaultException("The parameter `{$p}` is required for request `{$httpMethod}` for app: `{$this->app->getParamConfig('appName')}` !",400);
            }
        }
    }

    public function configRequest()
    {
        $config = $this->app->getParamConfig('config','public');
        $appName = $config['appName'];
        $className = sprintf("%sController",$this->app->getParamConfig('route.controller'));
        $namespace = "app\\$appName\\controllers\\$className";
        $namepaceParent = "kora\bin\\ControllerKora";
        $appPath = $config['appPath'];

        $path = "$appPath/controllers/$className.php";

        if(!file_exists($path) || !class_exists($namespace))
        {
            throw new DefaultException("controller {$className} does not exists!",404);
        }
        else if(!is_subclass_of($namespace,$namepaceParent))
        {
            throw new DefaultException("controller {$className} is not a subclass of ControllerKora!",404);
        }

        $controller = $this->app->getParamConfig('route.controller');
        $action = $this->app->getParamConfig('route.action');
        $cUrl = $this->app->getParamConfig('route.cUrl');
        $aUrl = $this->app->getParamConfig('route.aUrl');

        if(!method_exists($namespace,$action))
        {
            throw new DefaultException("Route {$controller}/{$action} not found!",404);
        }

        $requestOptions =     
        [
            'appPath' => $appPath,
            'className' => $this->app->getParamConfig('route.controller'),
            'controller' => $className,
            'action' => $this->app->getParamConfig('route.action'),
            'namespace' => $namespace,
            'route' => $this->app->getParamConfig('route.name'),
            'cUrl' => $cUrl,
            'aUrl' => $aUrl
        ];

        $this->app->setParamConfig('config.http.request',$requestOptions);

        $baseUrl = "{$this->Request->getSchemeAndHttpHost()}{$this->Request->getBaseUrl()}";

        $this->app->setParamConfig('config.http.request.baseUrl',$baseUrl);
        $this->app->setParamConfig('config.http.request.urlApp',"{$baseUrl}/app/{$appName}");
        $this->app->setParamConfig('config.http.request.urlViews',"{$baseUrl}/app/{$appName}/views");
        $this->app->setParamConfig('config.http.request.urlView',"{$baseUrl}/app/{$appName}/views/{$cUrl}");
        $this->app->setParamConfig('config.http.request.urlViewsSections',"{$baseUrl}/app/{$appName}/views/sections");
        $this->app->setParamConfig('config.http.request.urlViewsTemplates',"{$baseUrl}/app/{$appName}/views/templates");
    }

    public function paramsRequest()
    {
        $currentRoute = $this->app->getParamConfig('route');

        $httpMethod = mb_strtolower($this->Request->getMethod());

        if(!array_key_exists($httpMethod,$currentRoute['keyParams']))
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

        $params = $currentRoute['keyParams'][$httpMethod];

        $this->paramsRequestValidate($httpMethod,$params,$queryParameters,$formParameters);


   
        $allParams = array_merge($queryParameters,$formParameters);

        $this->app->setParamConfig('config.http',[
            'method' => $httpMethod,
            'parameters' => $allParams
        ]);
    }
}
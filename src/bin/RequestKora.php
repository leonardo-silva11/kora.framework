<?php
namespace kora\bin;

use kora\lib\collections\Collections;
use Symfony\Component\HttpFoundation\Request;
use kora\lib\exceptions\DefaultException;
use kora\lib\exceptions\InputException;
use kora\lib\strings\Strings;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

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

        $this->_httpOriginCheck()
        ->parseCurrentRoute()
             ->_configController()
             ->_configAction()
             ->_configUrl()
             ->_configPaths()
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

                throw new DefaultException("The parameter `{$p}` is not allowed or missed from request `{$httpMethod}` for app: `{$this->app->getParamConfig('app.name')}` !",400);
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
        $requestUri = $routeDefault != $requestUri ? str_ireplace(["/$nameOfApp",$nameOfApp],Strings::empty,$requestUri) : $requestUri;
        $requestUri = substr($requestUri,0,1) === '/' &&  mb_strlen($requestUri) > 1 ? substr($requestUri,1) : $requestUri;
        $requestUri = explode('?',$requestUri)[0];
        $requestUriCount = substr_count($requestUri,'/');
        //action default for controllers
        $requestUri = $requestUriCount > 0 ? $requestUri : "$requestUri/index";

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

    private function parseParameters($param, $parameters, &$result, &$parent = null)
    {
        $nameOfApp = $this->app->getParamConfig('app.name');
        $baseName = trim($param);
        $baseName =  implode('',array_map('ucfirst', explode('_',$baseName)));
        $namespace = "app\\$nameOfApp\\inputs\\{$baseName}Input";

        if(class_exists($namespace))
        {
            $result[$baseName] = array_key_exists($baseName,$result) ? $result[$baseName] : new $namespace();
            $refClass = new ReflectionClass($result[$baseName]);

            if($parent != null)
            {
                $parent->{$baseName} = $result[$baseName];
            }

            // Obtendo todas as propriedades
            $properties = $refClass->getProperties();

            if(empty($properties))
            {
                throw new InputException("There are no attributes defined in the class: {$baseName}Input",400);
            }

            $n1 = implode('',array_map('ucfirst', explode('_',$param)));
            $keys = [
                $param,
                $n1,
                implode('',array_map('mb_strtolower', explode('_',$param))),
                lcfirst($n1),
                strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $param))
            ];

            $keySearch = array_values(array_unique(array_filter($keys, fn($key) => array_key_exists($key, $parameters))));

            foreach ($properties as $property)
            {
                $this->parseParameters
                (
                    $property->getName(),
                    !empty($keySearch[0]) ? $parameters[$keySearch[0]] : $parameters, 
                    $result, 
                    $result[$baseName]
                );
            }
        }
        else
        {
            if(!array_key_exists($param,$parameters))
            {
                throw new InputException("The attribute {$param} is not allowed or missed in request!",400);
            }

            if($parent != null && property_exists($parent,$param))
            {
                $ref = new ReflectionProperty($parent,$param);
                $ref->setAccessible(true);
                $ref->setValue($parent,$parameters[$param]);
            }
            else
            {
                $result[$param] = $parameters[$param];
            }
        }
    }

    private function verifyParametersRequest($result, $parameters, $allParams, $params, $httpMethod)
    {
        $mappedParameters = [];

        foreach ($result as $className => $classInstance) 
        {
            if (in_array($className, $allParams)) 
            {
                $mappedParameters = array_merge($mappedParameters, array_keys((array)$classInstance));
            }
        }

        $mappedParameters = array_unique($mappedParameters);
        $noMappedParameters = array_diff(array_keys($parameters), $mappedParameters);

        foreach($noMappedParameters as $param)
        {
            $this->paramsRequestValidateAll($httpMethod,$params,$param);
        }
    }
   

    private function requestInputClass($queryParameters,$formParameters, $params, $httpMethod)
    {
        $result = [];

        $allParams = array_merge($params['required'],$params['optional']);
        $parameters = array_merge($queryParameters, $formParameters);

        foreach($allParams as $param)
        {
            $this->parseParameters($param,$parameters,$result);
        }

        $this->verifyParametersRequest($result, $parameters, $allParams, $params, $httpMethod);

        return $result;
    }


    private function validateObjectInput($requestInput, $httpMethod, $params)
    {
        foreach($requestInput as $k => $obj)
        { 
            if(is_object($obj))
            {  
                $obj->validate(); 
            }
        }
    }

    private function paramsRequestValidate($httpMethod,$params,$queryParameters,$formParameters)
    {
        $ignoreParameters = $this->app->getParamConfig('http.action.ignoreParameters');

        $requestInput = [];

        if(!$ignoreParameters)
        {
            $requestInput = $this->requestInputClass($queryParameters,$formParameters,$params,$httpMethod);
            $this->validateObjectInput($requestInput, $httpMethod, $params);
        }

        return $requestInput; 
    }

    private function _configAction()
    {
        $configAction = $this->app->getParamConfig('http.route.actions');
        $action = key($configAction);
        $middlewares = Collections::arrayKeyExistsInsensitive('middlewares',$configAction[$action]) ? $configAction[$action]['middlewares'] : [];
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
            'middlewares' => $middlewares,
            'controller' => $controller,
            'ignoreParameters' =>  $ignoreParameters
        ],'protected');

        return $this;
    }

    private function _httpOriginCheck()
    {
        if(!$this->config['ignoreOrigin'])
        {
            $origin = $this->config['http']['request']['instance']->headers->get('Origin')  ?? Strings::empty;

            if (!in_array($origin, $this->config['allowedOrigins'])) {
                
                //missed HTTP_ORIGIN header
                throw new DefaultException("the origin HTTP_ORIGIN was not available in the request",403);
            }
            header("Access-Control-Allow-Origin: $origin");
            // Permitir métodos HTTP específicos
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    
            // Permitir headers específicos na requisição
            header("Access-Control-Allow-Headers: Content-Type, Authorization");
    
            // Permitir o envio de cookies e outras credenciais
            header("Access-Control-Allow-Credentials: true");
       
            if ($this->config['http']['request']['instance']->getMethod() == 'OPTIONS') 
            {
                http_response_code(200);
                exit;
               
            }
    
        }

        return $this;
    }


    private function _configUrl()
    {
        $nameOfApp = $this->app->getParamConfig('app.name');
        //$nameOfproject = $this->app->getParamConfig('info.nameOfproject');
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

    private function _configPaths()
    {
        $nameOfApp = $this->app->getParamConfig('app.name');
        $pathOfProject = $this->app->getParamConfig('paths.pathOfProject');
        $dirSep = DIRECTORY_SEPARATOR;
        $this->app->setParamConfig('paths.app',"{$pathOfProject}{$dirSep}app{$dirSep}{$nameOfApp}",'protected');
        $this->app->setParamConfig('paths.views',"{$pathOfProject}{$dirSep}app{$dirSep}{$nameOfApp}{$dirSep}views",'protected');
        $this->app->setParamConfig('paths.public',"{$pathOfProject}{$dirSep}app{$dirSep}{$nameOfApp}{$dirSep}public",'protected');

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
    
        $formParameters = array_merge($formCollection['x-www-form-urlencoded'],$formCollection['form-data-body'],$formCollection['json'] ?? []);
        $queryParameters = $this->Request->query->all();
      
        $params = $route['keyParams'][$httpMethod];

        $requestInput = $this->paramsRequestValidate($httpMethod,$params,$queryParameters,$formParameters);

        $this->app->setParamConfig('http.route.params',[
            'method' => $httpMethod,
            'parameters' => $requestInput
        ],'protected');
       
        return $this;
    }
}
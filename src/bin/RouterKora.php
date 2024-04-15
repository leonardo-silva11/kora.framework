<?php 
namespace kora\bin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use JmesPath\Env;
use kora\lib\collections\Collections;
use kora\lib\exceptions\DefaultException;
use kora\lib\storage\DirectoryManager;
use kora\lib\strings\Strings;
use kora\lib\ViewTemplate\Template;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;

class RouterKora
{
    private static $request;
    private static $instance = null;
    private string $projectPath;
    private string $appPath;
    private AppKora $app;
    private RequestKora $RequestKora;
    private FilterKora $FilterKora;


    private function __construct(\Main $main)
    {
        RouterKora::$request = Request::createFromGlobals();

        $this->projectPath = dirname(__DIR__,5);
        $this->appPath = "$this->projectPath/app";

        $this->config($main);     
    }

    private function config(\Main $main)
    {

        $defaultStorage = $main->getDefaultStorage();    
        $pathSettings = "{$defaultStorage->getCurrentStorage()}{$defaultStorage->getDirectorySeparator()}appsettings.json";

        if(!file_exists($pathSettings))
        {
            throw new DefaultException("{{$pathSettings}} not found!");
        }

        $appSettings = $this->getAppSettings($pathSettings);
        $project = $main->getProject();

        $rqstUri = RouterKora::$request->getRequestUri();

        $parseUri = $this->uriToCollection($rqstUri);
        
        $defaultApp = $rqstUri === '/' || !$this->isApp($parseUri[0],$appSettings);
        
        $this->parseApp
        (
            $parseUri,
            $defaultApp,
            $appSettings,
            $defaultStorage
        );
    }

    private function uriToCollection(string $rqstUri) : array
    {
        $r = explode('/',$rqstUri);

        $r = array_filter($r, function ($value) 
        {
            return !empty($value) || $value === 0;
        });

        $r = array_values($r);
        
        return $r;
    }

    private function isApp(string $appName, Array $appSettings) : bool
    {
        return array_key_exists($appName,$appSettings['apps']);
    }

    private function parseRouteUrl(array $parseUri, int $param)
    {
        $p =  Strings::empty;

        if(!empty($parseUri[$param]))
        {
            $re = '`(^[A-z0-9])*(\?|\/).*`is';
            $p = mb_strtolower(preg_replace($re, Strings::empty, $parseUri[$param]));
            
        }

        return $p;
    }


    private function defaultRouteParse(Array &$matchRoute)
    {
            
        if(!empty($matchRoute['current']['cUrl']) && empty($matchRoute['current']['aUrl']))
        {
            $matchRoute['current']['aUrl'] = $matchRoute['default']['segmentPath'][1];
        }
        else if($matchRoute['default']['isDefaultApp'] && empty($matchRoute['current']['cUrl']) && empty($matchRoute['current']['aUrl']))
        {
            $matchRoute['current']['cUrl'] = $matchRoute['default']['segmentPath'][0];
            $matchRoute['current']['aUrl'] = $matchRoute['default']['segmentPath'][1];
        }
    }


    private function parseRequest(array $parseUri)
    {    
        $defaultRoute = $this->app->getParamConfig('config.defaultRoute','public');

        if(count($defaultRoute['segmentPath']) != 2)
        {
            throw new DefaultException("Default route: {$defaultRoute['defaultRoutePath']} is malformed in appsettings.json, the correct format is: `controller/action`");
        }
 
     
        //deve ter uma match com a controller do app
        $cUrl =  $this->parseRouteUrl($parseUri,0);
        //deve ter uma match com a action do app na controller
        $aUrl = $this->parseRouteUrl($parseUri,1);

        $matchRoute = [
            'default' => $defaultRoute,
            'current' => [
                'cUrl' => $cUrl,
                'aUrl' => $aUrl,
                'parseCurl' => $parseUri[0] ?? null,  
                'parseAurl' => $parseUri[1] ?? null 
            ]
        ];

        $this->defaultRouteParse($matchRoute);
        $this->app->parseRouteConfig($matchRoute);
    }

    private function setUpCall(array $parseUri)
    {
        $this->parseRequest($parseUri); 
     
        $this->RequestKora = new RequestKora($this->app,RouterKora::$request);
        $this->RequestKora->paramsRequest();
       
        $this->RequestKora->configRequest(); 


        $this->FilterKora = new FilterKora($this->app);
        $this->FilterKora->parseFilters();

        $this->callController();
    }

    private function parseApp
    (
        array $parseUri, 
        bool $isDefault, 
        array $appSettings, 
        DirectoryManager $defaultStorage
    )
    {
        $env = new Env();

        $defaultAppName = $env->search('defaultApp',$appSettings);
     
        if(empty($defaultAppName))
        {
            throw new DefaultException("Default app is not defined in appsettings.json");
        }

        $appName = $isDefault ? mb_strtolower($defaultAppName): mb_strtolower($parseUri[0]);

        if(!Collections::arrayKeyExistsInsensitive($appName,$appSettings['apps']))
        {
            throw new DefaultException("App {$appName} is not defined in appsettings.json in section {apps}");
        }

        $appConfig = Collections::getElementArrayKeyInsensitive($appName,$appSettings['apps']);

        $defaultRoute = $env->search("apps.{$appConfig['key']}",$appSettings);

        if(empty($defaultRoute))
        {
            throw new DefaultException("Default route app is not defined in appsettings.json for app $appName");
        }

        $className = $appConfig['key'];
        $namespace = "app\\$appName\\$className";
        $classPath = "$this->appPath/$appName/$className.php";
        
        if(!file_exists($classPath) || !class_exists($namespace))
        {
            throw new DefaultException("The app {$appName} class not found in: $this->appPath/$appName",500);
        }

        $config =  
        [
            'appName' => $appName,
            'className' => $className,
            'namespace' => $namespace,
            'appPath' => "$this->appPath/$appName",
            'classPath' => $classPath,
            'defaultRoute' => [
                'defaultRoutePath' => $defaultRoute['defaultRoute'],
                'isDefaultApp' => $isDefault,
                'segmentPath' => explode('/',$defaultRoute['defaultRoute']),
            ],
            'settings' => $appConfig,
            'request' => self::$request,
            'defaultStorage' => $defaultStorage
        ];

        $this->app = new $namespace(RouterKora::$request);

        $this->app->setParamConfig('config',$config);
        $this->app->setParamConfig('appSettings',$appSettings);
 
        $this->setUpCall($parseUri);
    
    }

    private function callController()
    {
        //Chama as configurações adicionadas nesse método
        $this->app->extraConfig();
        $serviceContainer  = new DependencyManagerKora($this->app);
        $constructorDependencies = $serviceContainer->resolveConstructorDependencies();
 
        $ctrNameClass = $this->app->getParamConfig('config.http.request.namespace','public');
        $ctrNameAction = $this->app->getParamConfig('config.http.request.aUrl','public');
        
        $controller = new $ctrNameClass(...$constructorDependencies);
        $parameters = $serviceContainer->filterRouteParameters($controller);

        //inject app in ControllerKora class
        $refMethod = new ReflectionMethod(ControllerKora::class, 'start');
        $refMethod->setAccessible(true); // Permitir acesso ao método privado
        $refMethod->invokeArgs(null,[$this->app]);
        $refMethod->setAccessible(false);
     
        $reflection = new ReflectionClass(IntermediatorKora::class);
        $m1 = $reflection->getMethod('start');
        $m1->setAccessible(true);
        $filterResponseBefore = $m1->invokeArgs(null, [$this->app, $serviceContainer,$this->FilterKora,$controller]);
        $m1->setAccessible(false);
      
        $keyBeforeFilter = "{$filterResponseBefore->__getShortName()}Before";
        $parameters[$keyBeforeFilter] = $filterResponseBefore;
        $responseController = $controller->$ctrNameAction(...$parameters);

        if($responseController instanceof Response)
        {
            $responseController->send();
        }
    }

    private function getAppSettings(string $pathSettings)
    {
        $str = file_get_contents($pathSettings);
        
        $settings = @json_decode($str,true);

        if(empty($settings))
        {
            throw new DefaultException("appsettings.json does not contains configurations!",500,[
                'info' => 'create a defaultApp key in appsettings.json and defined the default app for this project.'
            ]);
        }

        return $settings;
    }

    public static function start(\Main $main)
    {

        if(empty(RouterKora::$instance))
        {
            RouterKora::$instance = new RouterKora($main);
        }
    }
}
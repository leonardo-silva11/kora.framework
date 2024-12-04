<?php 
namespace kora\bin;

use Symfony\Component\HttpFoundation\Request;
use kora\lib\collections\Collections;
use kora\lib\exceptions\DefaultException;
use kora\lib\storage\DirectoryManager;
use kora\lib\strings\Strings;
use ReflectionClass;
use ReflectionMethod;

class RouterKora
{
    private static $instance = null;
    private AppKora $app;

    private function __construct(array $config, DirectoryManager $defaultStorage)
    {
        $this->config($config,$defaultStorage);    
    }

    private function config
        (
            array $config, 
            DirectoryManager $defaultStorage
        )
    {
        $config['http']['request']['instance'] = Request::createFromGlobals();

        $config['pathSettings'] = !$config['useSettingsInProject'] 
                        ? 
                        "{$defaultStorage->getCurrentStorage()}{$defaultStorage->getDirectorySeparator()}appsettings.json"
                        :
                        "{$config['pathOfProject']}{$defaultStorage->getDirectorySeparator()}appsettings.json";
        
        if(!file_exists($config['pathSettings']))
        {
            throw new DefaultException("{{$config['pathSettings']}} not found!");
        }


        $config['storage']['defaultStorage'] = $defaultStorage;
        $config['appSettings'] = $this->getAppSettings($config['pathSettings']);
        $config['http']['requestUri'] = $config['http']['request']['instance']->getRequestUri();
        $config['http']['requestUriCollection'] = $this->uriToCollection($config['http']['requestUri']);

        $this->parseApp($config);
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

    private function defineDefaultApp(array &$config): void
    {
        if(!is_array($config['appSettings']['apps']) || empty($config['appSettings']['apps']))
        {
            throw new DefaultException('The sections {apps} in file {appsettings.json} does not contains apps!',500);
        }

        $firstApp = key($config['appSettings']['apps']);
        $config['appSettings']['defaultApp'] = mb_strtolower($firstApp);
    }


    private function newInstanceApp(array &$config): void
    {
        $requestUri = $config['http']['requestUri'];
        $requestUriCollection = $config['http']['requestUriCollection'];
        
        if(empty($requestUriCollection))
        {
            array_push($requestUriCollection,$config['appSettings']['defaultApp']);
        }

        $app = mb_strtolower($requestUriCollection[0]);

        $config['app']['isDefault'] = $requestUri === '/' || mb_strtolower($requestUriCollection[0]) === $config['appSettings']['defaultApp'];
        $config['app']['name'] = $config['app']['isDefault'] ? $config['appSettings']['defaultApp'] : $app;
        $appConfig = Collections::getElementArrayKeyInsensitive($config['app']['name'],$config['appSettings']['apps']);
 
        if(empty($appConfig))
        {
            throw new DefaultException("app config in {appsettings.json} it's misconfigured or does not exist!",500);
        }

        $config['app']['class'] = $appConfig['key'] ?? $config['appSettings']['defaultApp'];
        $config['app']['config'] = $appConfig;

        if(empty($appConfig['key']))
        {
               foreach($config['appSettings']['apps'] as $nameApp => $appObj)
               {
                    if($appObj['name'] == $config['appSettings']['defaultApp'])
                    {
                        $config['app']['class'] = $nameApp;
                        $config['app']['config'] = $appConfig;
                        $config['app']['name'] = $appObj['name'];
                        break;
                    }
               } 
        }

        $Class = "app\\{$config['app']['name']}\\{$config['app']['class']}";
            //colocar validações de chaves app.name e app.class
        $path =  $config['pathOfProject'].DIRECTORY_SEPARATOR.
                "app".DIRECTORY_SEPARATOR.
                $config['app']['name'].DIRECTORY_SEPARATOR.
                $config['app']['class'].".php";

        if(!file_exists($path) || !class_exists($Class))
        {
            throw new DefaultException("The app {$config['app']['name']} class not found in: {$path}",500);
        }
    
        $config['app']['path'] = dirname($path);
        $config['app']['pathClass'] = $path;
        $config['app']['fullName'] = $Class;
 
        $this->app = new $Class($config);
    }

    private function parseApp(array &$config): void
    {

        $appSettings = $config['appSettings'];

        if(!Collections::arrayKeyExistsInsensitive('apps',$appSettings))
        {
            throw new DefaultException('The file {appsettings.json} does not contains definitions for {apps}!',500);
        }

        if(empty($appSettings['defaultApp']))
        {
            $this->defineDefaultApp($config);
        }

        $this->newInstanceApp($config);
       
        (new RequestKora($config));
        $this->callController(
            $config,
            (new MiddlewareKora($config))
        ); 
    }

    private function callController(array $config,MiddlewareKora $MiddlewareKora)
    {
        $this->app->execBeforeAction();

        $serviceContainer  = new DependencyManagerKora($config);
        $constructorDependencies = $serviceContainer->resolveConstructorDependencies();
        $this->app->block();

        //inject parameters in super class
        $refMethod = new ReflectionMethod(ControllerKora::class, 'start');
        $refMethod->setAccessible(true);
        $refMethod->invokeArgs(null,[$this->app]);
        $refMethod->setAccessible(false);

        $controllerClass = $this->app->getParamConfig('http.controller.namespace');
        $controller = new $controllerClass(...$constructorDependencies);

        $reflection = new ReflectionClass(IntermediatorKora::class);
        $m1 = $reflection->getMethod('start');
        $m1->setAccessible(true);
        $response = $m1->invokeArgs(null, [$this->app, $serviceContainer,$MiddlewareKora,$controller]);
        $m1->setAccessible(false);

        if($response instanceof BagKora)
        {       
            $this->app->addInjectable($response->getName(),$response);
          
            $parameters = $serviceContainer->filterRouteParameters($controller);
            $action = $this->app->getParamConfig('http.action.name');
            $services = $serviceContainer->resolveRouteDependencies();
            $parameters += $services;
            $response = $controller->$action(...$parameters);
        }

        if(!($response instanceof IMenssengerKora))
        {
            throw new DefaultException(sprintf('Response must be an instance of %s!',IMenssengerKora::class),500);
        }
        
        $this->app->execAfterAction();

        $response->send();
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

    public static function start(array $config, $defaultStorage)
    {
        if(empty(RouterKora::$instance))
        {
            RouterKora::$instance = new RouterKora($config, $defaultStorage);
        }
    }
}
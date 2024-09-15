#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use kora\lib\exceptions\DefaultException;
use kora\lib\storage\DirectoryManager;
use kora\lib\storage\FileManager;

class MakeConfig extends CommandCli
{
    private const _APP_SETTINGS = 'appsettings';
    private const _ROUTES = 'routes';
    private string $appName;
    private string $project;
    private array $appSettings = [];
    private array $routes = [];
    private string $currentRouteJson;
    private DirectoryManager $dirConfig;
    private FileManager $appConfig;

    public function exec(array $arg){}

    public function __construct(string $appName)
    {
        $pathProject = dirname(__DIR__,6);
        parent::__construct($this, $pathProject);

        $this->appName = $appName;
        $this->project = basename($pathProject);

        $this->dirConfig = new DirectoryManager($this->project);

        $dirFile = $this->dirConfig->cloneStorage();

        $this->appConfig = new FileManager($dirFile);
    }
    

    public function addRoute(string $alias, string $controller, string $action, string $verb = 'get', bool $overwrite = false)
    {
        $this->readRoutesFromJson();

        if(!array_key_exists('routes',$this->routes))
        {
            $this->routes['routes'] = [];
        }

        $this->currentRouteJson = json_encode($this->routes['routes']);

        if(
            (   
                !array_key_exists($alias,$this->routes['routes']) 
                || !array_key_exists("controller",$this->routes['routes'][$alias]) 
                || !array_key_exists($action,$this->routes['routes'][$alias]['actions'])
            )
            ||
            $overwrite
        )
        {
   
            $this->routes['routes'][$alias]['controller'] = $controller;
            $this->routes['routes'][$alias]['actions'][$action] = [
                "verbs" =>  [
                    mb_strtolower($verb) => []
                ],
                "filters" =>
                [
                    "after" =>
                    [

                    ],
                    "before" =>
                    [

                    ]
                ]
            ];
        }
    }

    public function routesSave(bool $overwrite = false)
    {
        if(!$this->appConfig->exists("$this->appName.json") || $overwrite || $this->currentRouteJson != $this->getRoutesJson())
        {
            $this->appConfig->save("$this->appName.json",$this->getRoutesJson());
            $this->log->save("$this->appName.json created!",false);
            return true;
        }

        $this->log->save("$this->appName.json alredy exists!",false);
        $this->log->showAllBag(false);
        return false;
    }

    public function settingsSave(bool $overwrite = false)
    {
        if(!$this->appConfig->exists('appsettings.json') || $overwrite)
        {
            $this->appConfig->save('appsettings.json',$this->getSettingsJson());
            $this->log->save('appsettings.json created!',false);
            return true;
        }

        $this->log->save('appsettings.json alredy exists!',false);
        $this->log->showAllBag(false);
        return false;
    }

    public function readRoutesFromJson(): array
    {
        $json = $this->appConfig->read("$this->appName.json");
        
        if($json != null)
        {
            $this->routes = \json_decode($json,true);
        }
        
        return $this->routes;
    }

    public function readSettingsFromJson(): array
    {
        $json = $this->appConfig->read('appsettings.json');

        if($json != null)
        {
            $this->appSettings = \json_decode($json,true);
        }
        
        return $this->appSettings;
    }

    public function readSettingsByKey(string $key)
    {
        $this->readSettingsFromJson();
        
        $keyArray = explode('.',$key);
        
        $appSettings = &$this->appSettings;

        for($i = 0; $i < count($keyArray);++$i)
        {
            $k = $keyArray[$i];

            if(array_key_exists($k,$appSettings))
            {
                $appSettings = &$appSettings[$k];
            }
            else
            {
                throw new DefaultException("key {$k} not found in appsettings.json!",500);
            }
        }

        return $appSettings;
    }

    public function getSettingsJson()
    {
        return \json_encode($this->appSettings,JSON_PRETTY_PRINT);
    }

    public function getRoutesJson()
    {
        return \json_encode($this->routes,JSON_PRETTY_PRINT);
    }

    public function addSetting(string $key, mixed $value, $rewrite = false)
    {
        $this->readSettingsFromJson();

        $keyArray = explode('.',$key);

        $appSettings = &$this->appSettings;
    
        for($i = 0; $i < count($keyArray);++$i)
        {
            $k = $keyArray[$i];

            if(array_key_exists($k,$appSettings))
            {
                $appSettings = &$appSettings[$k];
            }
            else
            {
                $appSettings[$k] = $value;
                break;
            }
        }

        return $this;
    }
}
<?php 
namespace kora\bin;

use kora\lib\collections\Collections;
use kora\lib\exceptions\DefaultException;
use Symfony\Component\HttpFoundation\Request;

abstract class AppKora
{
    private Array $app = [];
    protected array $injectables = [];
    public static string $directorySeparator = DIRECTORY_SEPARATOR;
    
    protected function __construct(AppKora $app, array &$config)
    {
        $config['app']['instance'] = $app;
        $config['app']['routes'] = $this->getRoutes($config);
       
        $this->app = 
        [
            'updated' => false,
            'blocked' => false,
            'public' => [],
            'private' => $config,
            'protected' => 
            [
                'app' => $config['app'],
                'http' => $config['http'],
                'appSettings' => $config['appSettings'],
                'storage' => $config['storage'],
                'info' => [
                    'nameOfproject' =>  $config['nameOfProject'],
                ],
                'paths' => 
                [
                    'pathOfProject' => $config['pathOfProject']
                ]
            ]
        ];
    }

    public abstract function execBeforeAction() : void;
    public abstract function execAfterAction() : void;

    public function injectables(): array
    {
        return $this->injectables;
    }

    public function getName()
    {
        return $this->getParamConfig('app.class');
    }

    public function addInjectable(string $key, mixed $item)
    {
        if(!array_key_exists($key,$this->injectables) && !empty($key) && !empty($item))
        {
            $this->injectables[$key] = $item;
            return true;
        }

        return false;
    }

    private function getRoutes(array &$config)
    {
        $storageDefault = $config['storage']['defaultStorage'];
        $pathRoutes = !$config['useRoutesInProject']
                      ?
                      "{$storageDefault->getCurrentStorage()}{$storageDefault->getDirectorySeparator()}{$config['app']['name']}.json"
                      :
                      "{$config['pathOfProject']}{$storageDefault->getDirectorySeparator()}{$config['app']['name']}.json"; 


        
        if(!file_exists($pathRoutes))
        {
            throw new DefaultException("{{$config['app']['name']}.json}} not found in {{$pathRoutes}}!",500,[
                'info' => "impossible to determine the application routes due to the absence of the file!"
            ]);
        }              
        $jsonFile = file_get_contents($pathRoutes);
        $routes = @json_decode($jsonFile,true);

        if(empty($routes) || !array_key_exists('routes',$routes) || empty($routes['routes']))
        {
            throw new DefaultException("App {{$config['app']['name']}} does not contain route definitions in the file:
                                     {{$config['app']['name']}.json}}!",500);
        }

        return $routes;
    }

    public function parseRouteConfig(array &$config)
    {
        if(!$this->app['blocked'])
        {
            $this->setParamConfig('http.route',$config['http']['route'],'protected');
            $this->parseCurrentAllowedKeyParams($config); 
        }
    }

    private function parseCurrentAllowedKeyParams(array &$config)
    {
      
        $configAction = $this->getParamConfig('http.route.actions');
        $action = key($configAction);
        $verbs = $configAction[$action]['verbs'];
        $this->app['protected']['http']['route']['keyParams'] = []; 
   
        if(!empty($verbs))
        {
            foreach($verbs as $k => $verb)
            {
                $kParam = mb_strtolower($k);

                $this->app['protected']['http']['route']['keyParams'][$kParam] = 
                [
                    'optional' => [],
                    'required' => []
                ]; 

                $countVerbs = count($verb);

                for($i = 0; $i < $countVerbs; ++$i)
                {
                    if(mb_substr($verb[$i],0,1) != '?' && mb_substr($verb[$i],0,1) != ':')
                    {
                        array_push($this->app['protected']['http']['route']['keyParams'][$k]['required'],$verb[$i]);
                    }
                    else if(mb_substr($verb[$i],0,1) == ':')
                    {
                        $key = mb_substr($verb[$i],1);
                      
                        $extends = array_key_exists($key,$verbs) ? $verbs[$key] : [];
                     
                        for($e = 0; $e < count($extends);++$e)
                        {
                            $keyType = mb_substr($extends[$e],0,1) != '?' ? 'required' : 'optional';
                            $valueKey = $keyType != 'required' ? mb_substr($extends[$e],1) : $extends[$e];
    
                            array_push($this->app['protected']['http']['route']['keyParams'][$k][$keyType],$valueKey);
                        }
                    }
                    else if(mb_substr($verb[$i],0,1) == '?')
                    {
                        $key = mb_substr($verb[$i],1);
                        array_push($this->app['protected']['http']['route']['keyParams'][$k]['optional'],$key);
                    }
                }
            }
        }

      
    }

    public function setParamConfig($key, $value, $level = 'public')
    {        
        $keyLevel = $level == 'public' ? $level : (!$this->app['blocked'] ? $level : 'public'); 

        if(!Collections::arrayKeyExistsInsensitive($keyLevel,$this->app))
        {
            throw new DefaultException("{$keyLevel} is invalid to set parameter config!",404);
        }
        else if(empty($key))
        {
            throw new DefaultException('The key is invalid',400);
        }

        $search = Collections::searchInDepthArrayCollection($key,[],$this->app[$keyLevel]);
       
        $pathKeys = explode('.',!empty($search['value']) ? $search['pathKey'] : $key);
        $pathKeys = array_filter($pathKeys);
    
        $collection = &$this->app[$keyLevel];

        for($i = 0; $i < count($pathKeys);++$i)
        {
            if(array_key_exists($pathKeys[$i],$collection))
            {
                $collection = &$collection[$pathKeys[$i]];
            }
        }   

        $keyToAdd = !empty($search['value']) ? end($search['segmentParts']) : end($pathKeys);

        $collection[$keyToAdd] = $value;

        return false;
    }

    public function getParamConfig(string $key,string $source = 'protected',bool $throwExcpt = true)
    {
        if($source != 'protected' && $source != 'public')
        {
            throw new DefaultException("{{$key}} is invalid to access parameter config, allowed sources are: {public} or {protected}!",404);
        }
        else if(!Collections::arrayKeyExistsInsensitive($source,$this->app))
        {
            throw new DefaultException("{{$source}} is invalid to access parameter config!",404);
        }

        $search = Collections::searchInDepthArray($key,$this->app[$source]);

        if($throwExcpt && $search === null)
        {
            throw new DefaultException("{{$key}} not found in configuration access {$source}!",404);
        }

        return $search;
    }

    public function block()
    {
        $this->app['blocked'] = true;
    }
}
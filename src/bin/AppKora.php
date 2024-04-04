<?php 
namespace kora\bin;

use JmesPath\Env;
use kora\lib\collections\Collections;
use kora\lib\exceptions\DefaultException;
use Symfony\Component\HttpFoundation\Request;

use function JmesPath\search;

abstract class AppKora
{
    private Array $app = 
    [
        'public' => [],
        'protected' => [],
        'private' => []
    ];
    
    public static string $directorySeparator = DIRECTORY_SEPARATOR;
    
    protected function __construct(AppKora $app, Request $request)
    {
         $projectPath = dirname(__DIR__,5);

         $shortName = $this->getAppShortName($app);
         $appName = mb_strtolower($shortName);   

         $this->app['private'] = [
            'instance' => $app
         ];

         $this->app['protected'] = [
                    'fullName' => $app::class,
                    'shortName' => $shortName,
                    'appName' => $appName,
                    'appPath' => "$projectPath/app/$appName",
                    "routes" => []
         ];

         $this->app['private']['request'] = $request;
         $this->loadRoutes();
    }

    public abstract function extraConfig() : void;

    public abstract function injectables() : array;

    private function getAppShortName(AppKora $app) : string
    {
        $p = explode('\\',$app::class);
        return $p[count($p) - 1];
    }

    private function loadRoutes()
    {
        $appPath = $this->app['protected']['appPath'];

        if(!file_exists("$appPath/route.json"))
        {
            throw new DefaultException("route.json not found in $appPath!",500,[
                'info' => "create and configure file: route.json in $appPath."
            ]);
        }

        $str = file_get_contents("$appPath/route.json");
        $routes = @json_decode($str,true);

        if(empty($routes))
        {
            $appName = $this->app['appName'];
            throw new DefaultException("route.json does not contains definition for app: $appName!",500,[
                'info' => "config the route.json file in app: $appName."
            ]);
        }

        $this->app['protected']['routes'] = $routes;
    }

    


    public function parseRouteConfig(array $matchRoute)
    {

       // dd($this->app['protected']['routes'],$matchRoute);


        $routeCtr = Collections::getElementArrayKeyInsensitive($matchRoute['current']['cUrl'],$this->app['protected']['routes']);

        if(empty($routeCtr['element']['actions']))
        {
            throw new DefaultException("{$matchRoute['current']['cUrl']} not found in route.json file!",404);
        }

        $routeAction = Collections::getElementArrayKeyInsensitive($matchRoute['current']['aUrl'],$routeCtr['element']['actions']); 

        if(empty($routeAction['key']) || empty($routeAction['element']))
        {
            throw new DefaultException("not found or incomplete route {$routeCtr['key']}/{$routeAction['key']} route.json file!",404);
        }

        $cUrl = trim($routeCtr['key']);
        $aUrl = trim($routeAction['key']);
        /****
        ** Verifica se a requisição é válida e está configurada em route.json
        ** Caso contrário dispara um DefaultException com código http 404
        */
        $this->app['protected']['route'] = $routeAction['element'];
            /*isset($this->app['protected']['routes'][$cUrl]['actions'][$aUrl]) ? 
            $this->app['protected']['routes'][$cUrl]['actions'][$aUrl] : 
            throw new DefaultException("Route: {$cUrl}/{$aUrl} not found!",404);*/

        if(empty($this->app['protected']['route']['path']))
        {
            throw new DefaultException("missed route.json parameter {path}!",404,['info' => 'path parameter is required for internal configuration.']);
        }

        $pathParts = explode('/',$this->app['protected']['route']['path']);

        if(count($pathParts) != 2 || !(strcasecmp($cUrl, $pathParts[0]) === 0) || !(strcasecmp($aUrl, $pathParts[1]) === 0))
        {
            throw new DefaultException("invalid route.json parameter {path}!",500,['info' => 'The parameter {path} is invalid, check your route.']);
        }

        Collections::addElementInFirstPositionArray('action',$pathParts[1],$this->app['protected']['route']);
        Collections::addElementInFirstPositionArray('controller',$pathParts[0],$this->app['protected']['route']);
        Collections::addElementInFirstPositionArray('aUrl',$aUrl,$this->app['protected']['route']);
        Collections::addElementInFirstPositionArray('cUrl',$cUrl,$this->app['protected']['route']);
        Collections::addElementInFirstPositionArray('name',"$cUrl/$aUrl",$this->app['protected']['route']);

        $this->parseCurrentAllowedKeyParams();  
    }

    private function parseCurrentAllowedKeyParams()
    {

        $this->app['protected']['route']['keyParams'] = []; 

        if(!empty($this->app['protected']['route']['verbs']))
        {
            foreach($this->app['protected']['route']['verbs'] as $k => $verb)
            {
                $kParam = mb_strtolower($k);

                $this->app['protected']['route']['keyParams'][$kParam] = [
                    'optional' => [],
                    'required' => []
                ]; 

                $countVerbs = count($verb);

                for($i = 0; $i < $countVerbs; ++$i)
                {
                    if(mb_substr($verb[$i],0,1) != '?' && mb_substr($verb[$i],0,1) != ':')
                    {
                        array_push($this->app['protected']['route']['keyParams'][$k]['required'],$verb[$i]);
                    }
                    else if(mb_substr($verb[$i],0,1) == ':')
                    {
                        $key = mb_substr($verb[$i],1);
                      
                        $extends = array_key_exists($key,$this->app['protected']['route']['verbs']) ? $this->app['protected']['route']['verbs'][$key] : [];
                     
                        for($e = 0; $e < count($extends);++$e)
                        {
                            $keyType = mb_substr($extends[$e],0,1) != '?' ? 'required' : 'optional';
                            $valueKey = $keyType != 'required' ? mb_substr($extends[$e],1) : $extends[$e];
    
                            array_push($this->app['protected']['route']['keyParams'][$k][$keyType],$valueKey);
                        }
                    }
                    else if(mb_substr($verb[$i],0,1) == '?')
                    {
                        $key = mb_substr($verb[$i],1);
                        array_push($this->app['protected']['route']['keyParams'][$k]['optional'],$key);
                    }
                }
            }
        }
    }

    public function setParamConfig($key,$value)
    {        
        $search = Collections::searchInDepthArrayCollection($key,[],$this->app['public']);

        if(empty($key))
        {
            throw new DefaultException('The key is invalid',400);
        }
       
        $pathKeys = explode('.',!empty($search['value']) ? $search['pathKey'] : $key);
        $pathKeys = array_filter($pathKeys);
    

        $collection = &$this->app['public'];

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
            throw new DefaultException("{$key} is invalid to access parameter config, allowed sources are: {public} or {protected}!",404);
        }

        $search = Collections::searchInDepthArray($key,$this->app[$source]);

        if($throwExcpt && $search === null)
        {
            throw new DefaultException("{$key} not found in configuration access {$source}!",404);
        }

        return $search;
    }
}
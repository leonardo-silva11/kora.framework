<?php 
namespace kora\bin;

use JmesPath\Env;
use kora\lib\exceptions\DefaultException;

abstract class AppKora
{
    private Array $app = [];

    protected function __construct(AppKora $app)
    {
         $projectPath = dirname(__DIR__,5);

         $shortName = $this->getAppShortName($app);
         $appName = mb_strtolower($shortName);   

         $this->app = [
                    'fullName' => $app::class,
                    'shorName' => $shortName,
                    'appName' => $appName,
                    'app' => $app,
                    'appPath' => "$projectPath/app/$appName",
                    "routes" => []
         ];

         $this->loadRoutes();
    }


    private function getAppShortName(AppKora $app) : string
    {
        $p = explode('\\',$app::class);
        return $p[count($p) - 1];
    }

    private function loadRoutes()
    {
        $appPath = $this->app['appPath'];

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

        $this->app['routes'] = $routes;
    }

    public function getRoute(string $key = null) : mixed
    {
        return !empty($key) && isset($this->app['routes'][$key]) ? $this->app['routes'][$key] : $this->app['routes'];
    }
}
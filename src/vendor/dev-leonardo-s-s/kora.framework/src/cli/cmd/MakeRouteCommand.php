#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use stdClass;

class MakeRouteCommand extends CommandCli
{
    public function __construct(string $path)
    {
        parent::__construct($this,$path);
    }


    public function exec(array $arg)
    {
        $routesApp = \json_decode(file_get_contents($this->paths['routeJsonFile']),true);
        $controller = OptionsCli::getOption('--c',$this->cmdArgs);
        $action = OptionsCli::getOption('--a',$this->cmdArgs);

        if(!empty($routesApp[$controller]['actions'][$action]))
        {
            $this->log->save("Route {{$controller}/{$action}} already exists!",true);
        }

        $keyController = mb_strtolower($controller);
        $routesApp[$keyController]['actions'][$action] = [
            'path' =>  "{$controller}/{$action}",
            'verbs' => [
                "get" => []
            ],
            'filters' => 
            [
                'before' => new stdClass(),
                'after' => new stdClass()
            ]

        ];

        $json = json_encode($routesApp,JSON_PRETTY_PRINT);

        $this->saveFile($this->paths['routeJsonFile'],$json,true);

        $this->log->save("Sucessfuly create route {{$controller}/{$action}} for app {{$this->app['lowerName']}}!",true);
    }
}

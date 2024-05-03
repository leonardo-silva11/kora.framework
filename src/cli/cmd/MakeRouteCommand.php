#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use kora\lib\strings\Strings;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Filesystem\Filesystem;


class MakeRouteCommand extends CommandCli
{
    public function __construct(string $path)
    {
        parent::__construct($this,$path);
    }


    public function exec()
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
                'before' => [],
                'after' => []
            ]

        ];

        $json = json_encode($routesApp,JSON_PRETTY_PRINT);

        $this->saveFile($this->paths['routeJsonFile'],$json,true);

        $this->log->save("Sucessfuly create route {{$controller}/{$action}} for app {{$this->app['lowerName']}}!",true);
    }
}

#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Filesystem\Filesystem;


class MakeControllerCommand
{
    public function __construct(){}

    public function exec()
    {
        $app = new Application();

        $app->register('make:controller')
        ->addArgument('name', InputArgument::REQUIRED)
        ->addOption('app', null, InputOption::VALUE_REQUIRED)
        ->addOption('action',null, InputOption::VALUE_OPTIONAL)
        ->setCode(function ($input, $output) {
            $controller = $input->getArgument('name'); // Use $controller em vez de $name
            $app = $input->getOption('app');
            $action = $input->getOption('action');

            if ($app === null) 
            {
                $output->writeln('<error>the --app option is missing.</error>');
                return 1;
            }
    
            $output->writeln("Gerando controller: {$controller}...");
    
            $this->createController($app, $controller, $action);
        });

        $app->run();
    
    }

    public function createController($app, $controller, $action)
    {
        $basePath = dirname(__DIR__, 1);

        $base = file_get_contents("$basePath/skeleton/ControllerSkeleton.kora");

        dump($base,$app, $controller, $action);

        exit;

  

        $appPath = "$basePath/app";
        $publicPath = "$basePath/public";

        $paths = [
                    "$appPath/$appName/controllers",
                    "$appPath/$appName/models",
                    "$appPath/$appName/intermediates",
                    "$publicPath/$appName/views"
                 ];

        $fs = new Filesystem();

        for($i = 0; $i < count($paths); ++$i)
        {
            if(!$fs->exists($paths[$i]))
            {
                $output->writeln("creating directory: $paths[$i]");
                $fs->mkdir($paths[$i], 0774, true);
            }
        }
    }
}

#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Filesystem\Filesystem;


class MakeAppCommand
{
    public function __construct(){}

    public function exec()
    {
        $app = new Application();

        $app->register('make:app')
            ->addArgument('name', InputArgument::REQUIRED)
            ->addOption('controller',null,InputOption::VALUE_OPTIONAL)
            ->addOption('action',null,InputOption::VALUE_OPTIONAL)
            ->addOption('type',null,InputOption::VALUE_OPTIONAL)
            ->setCode(function ($input, $output) 
            {
                $name = $input->getArgument('name');
                $controller = $input->getOption('controller');
                $action = $input->getOption('action');
                $type = $input->getOption('type');

                $controller = $controller ?? 'Home';
                $action = $action ?? 'index';
                $type = $type ?? 'app';

                $output->writeln("Criando aplicativo: {$name}...");
                $this->createDirectories($name, $output);
                $this->createAppClass($name);
                $this->createController($name, $controller, $action);
                
            });
            $app->run();
    }

    private function createController($app, $controller, $action)
    {
        (new MakeControllerCommand())->createController($app, $controller, $action);
    }

    private function createAppClass($appName)
    {
        $basePath = dirname(__DIR__, 1);
        $appPath =  dirname($basePath, 5);
        $className = ucfirst($appName);
        $classPath = "$appPath/app/$appName/$className.php";
        $fs = new Filesystem();

        if(!$fs->exists($classPath))
        {
            $base = file_get_contents("$basePath/skeleton/AppSkeleton.kora");

        

            $appClass = str_ireplace
                            (
                                [
                                    '{{__namespace}}',
                                    '{{__name}}',
                                    '{{__alias}}'
                                ],
                                [
                                    $appName,
                                    ucfirst($appName),
                                    $appName != 'AppKora' ? 'AppKora' : 'AppKoraAbstract'
                                ],
                                $base
                            );
            
           
            file_put_contents($classPath,$appClass); 
        }   
    }

    private function createDirectories($appName,$output)
    {
        $basePath = dirname(__DIR__, 6);

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

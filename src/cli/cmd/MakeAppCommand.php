#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use Directory;
use kora\lib\storage\DirectoryManager;
use kora\lib\storage\FileManager;

class MakeAppCommand  extends CommandCli
{
    public function __construct(string $path)
    {
        parent::__construct($this,$path);
    }

    public function exec()
    {
        dd('vamos começar a criar o app');
    }

    public function createControllerClass(DirectoryManager $dirManager,$app, $controller, $action)
    {
        $MakeController = new MakeControllerCommand($this->paths['project']);
        $MakeController->createController($dirManager, $app, $controller, $action);
    }

    public function createmodelClass(DirectoryManager $dirManager,$app, $model, $action)
    {
        $MakeController = new MakeModelCommand($this->paths['project']);
        $MakeController->createModel($dirManager, $app, $model, $action);
    }


    public function creatAppClass(DirectoryManager $dirManager,$nameApp, $rewrite = false)
    {
        $basePath = dirname(__DIR__, 1);

        $file = new FileManager($dirManager);

        if(!$file->exists("$nameApp.php") || $rewrite)
        {
            $base = file_get_contents("$basePath/skeleton/AppSkeleton.kora");

            $fileClass = str_ireplace(                                
            [
                '{{__name}}',
            ],
            [
                $nameApp,
            ],$base);

            $file->save("$nameApp.php",$fileClass);

            $this->log->save("Class {$nameApp} created!");
        }
        else
        {
            $this->log->save("Class {$nameApp} alredy exists!");
        }

        $this->log->showAllBag(false);
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

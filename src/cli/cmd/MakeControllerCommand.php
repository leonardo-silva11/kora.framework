#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use kora\lib\storage\DirectoryManager;
use kora\lib\storage\FileManager;

class MakeControllerCommand extends CommandCli
{

    public function __construct($path)
    {
        parent::__construct($this, $path);
    }

    public function exec()
    {
        $basePath = dirname(__DIR__, 1);
        $baseFile = file_get_contents("$basePath/skeleton/ControllerSkeleton.kora");
        $this->createController($baseFile);
    }

    public function createController(DirectoryManager $dir, $app, $controller, $action, bool $rewrite = false)
    {
        $nameController = ucfirst($controller);
        $nameFullController = $nameController.'Controller';

        $basePath = dirname(__DIR__, 1);

        $pathControllers = $dir->createInMemory($dir->getCurrentStorage(),"controllers");
        $dirController = $dir->createByPath($pathControllers);

        $file = new FileManager($dirController);

        if(!$file->exists("$nameFullController.php") || $rewrite)
        {
            $nameAppLower = strtolower($app);
            $MakeConfig = new MakeConfig($nameAppLower);
            $type = $MakeConfig->readSettingsByKey("apps.$app.defaultType");

            if($type == 'app')
            {
                $base = file_get_contents("$basePath/skeleton/ControllerSkeletonApp.kora");
            }
            else
            {
                $base = file_get_contents("$basePath/skeleton/ControllerSkeletonApi.kora");
            }

                $fileClass = str_ireplace(                                
                [
                    '{{__nameController}}',
                    '{{__nameApp}}',
                    '{{__action}}'
                ],
                [
                    $nameController,
                    $nameAppLower,
                    $action
                ],$base);

            $file->save("$nameFullController.php",$fileClass);

            $this->log->save("Class {$nameFullController} created!");
        }
        else
        {
            $this->log->save("Class {$nameFullController} alredy exists!");
        }
        $this->log->showAllBag(false);
    }
}

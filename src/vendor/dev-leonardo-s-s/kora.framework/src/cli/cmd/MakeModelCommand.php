#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use kora\lib\storage\DirectoryManager;
use kora\lib\storage\FileManager;

class MakeModelCommand extends CommandCli
{

    public function __construct($path)
    {
        parent::__construct($this, $path);
    }

    public function exec(array $arg){}

    public function createModel(DirectoryManager $dir, $app, $Model, $action, bool $rewrite = false)
    {
        $nameModel = ucfirst($Model);
        $nameFullModel = $nameModel.'Model';

        $basePath = dirname(__DIR__, 1);

        $pathModels = $dir->createInMemory($dir->getCurrentStorage(),"models");
        $dirModel = $dir->createByPath($pathModels);

        $file = new FileManager($dirModel);

        if(!$file->exists("$nameFullModel.php") || $rewrite)
        {
            $base = file_get_contents("$basePath/skeleton/ModelSkeleton.kora");

            $nameAppLower = strtolower($app);

            $fileClass = str_ireplace(                                
            [
                '{{__nameModel}}',
                '{{__nameApp}}',
                '{{__action}}'
            ],
            [
                $nameModel,
                $nameAppLower,
                $action
            ],$base);

            $file->save("$nameFullModel.php",$fileClass);

            $this->log->save("Class {$nameFullModel} created!");
        }
        else
        {
            $this->log->save("Class {$nameFullModel} alredy exists!");
        }

        $this->log->showAllBag(false);
    }
}

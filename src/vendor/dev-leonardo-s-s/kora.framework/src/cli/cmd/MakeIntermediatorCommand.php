#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use kora\lib\storage\DirectoryManager;
use kora\lib\storage\FileManager;

class MakeIntermediatorCommand extends CommandCli
{

    public function __construct($path)
    {
        parent::__construct($this, $path);
    }

    public function exec(array $arg){}

    public function createIntermediator(DirectoryManager $dir, $app, $Intermediator, $action, bool $rewrite = false)
    {
        $nameIntermediator = ucfirst($Intermediator);
        $nameFullIntermediator = $nameIntermediator.'Intermediator';

        $basePath = dirname(__DIR__, 1);

        $pathIntermediators = $dir->createInMemory($dir->getCurrentStorage(),"intermediators");
        $dirIntermediator = $dir->createByPath($pathIntermediators);

        $file = new FileManager($dirIntermediator);

        if(!$file->exists("$nameFullIntermediator.php") || $rewrite)
        {
            $base = file_get_contents("$basePath/skeleton/IntermediatorSkeleton.kora");

            $nameAppLower = strtolower($app);

            $fileClass = str_ireplace(                                
            [
                '{{__nameIntermediator}}',
                '{{__nameApp}}',
                '{{__action}}'
            ],
            [
                $nameIntermediator,
                $nameAppLower,
                $action
            ],$base);

            $file->save("$nameFullIntermediator.php",$fileClass);

            $this->log->save("Class {$nameFullIntermediator} created!");
        }
        else
        {
            $this->log->save("Class {$nameFullIntermediator} alredy exists!");
        }

        $this->log->showAllBag(false);
    }
}

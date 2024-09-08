#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use kora\lib\storage\DirectoryManager;
use kora\lib\storage\FileManager;

class MakeViewCommand extends CommandCli
{

    public function __construct($path)
    {
        parent::__construct($this, $path);
    }

    public function exec(array $arg){}


    public function createPublic(DirectoryManager $dir, $template, bool $rewrite = false)
    {
        $directoryTemplates = "public/templates/$template";
        $pathPublic = $dir->createInMemory($dir->getCurrentStorage(),$directoryTemplates);

        if(!$dir->directoryExists($directoryTemplates))
        {
            $dir->createByPath($pathPublic);
            $this->log->save("directory {$directoryTemplates} created!");
        }
        else
        {
            $this->log->save("directory {$directoryTemplates} alredy exists!");
        }
     
        $this->log->showAllBag(false);
    }

    public function createView(DirectoryManager $dir, $nameApp, $view, $action, $template, $extension, bool $rewrite = false)
    {

        $this->createPublic($dir,$template);

        $basePath = dirname(__DIR__, 1);

        $pathViews = $dir->createInMemory($dir->getCurrentStorage(),"views/$view/$template");
        $dirView = $dir->createByPath($pathViews);

        $file = new FileManager($dirView);

        $fileName = "$action.$extension";

        if(!$file->exists($fileName) || $rewrite)
        {
            $base = file_get_contents("$basePath/skeleton/ViewSkeleton.kora");

            $fileClass = str_ireplace(                                
            [
                '{{__nameApp}}',
                '{{__currentDate}}',
            ],
            [
                $nameApp,  
                (new \DateTime())->format('d/m/Y')
            ],$base);

            $file->save($fileName,$fileClass);

            $this->log->save("Filename {$fileName} created!");
        }
        else
        {
            $this->log->save("Filename {$fileName} alredy exists!");
        }

        $this->log->showAllBag(false);
    }
}

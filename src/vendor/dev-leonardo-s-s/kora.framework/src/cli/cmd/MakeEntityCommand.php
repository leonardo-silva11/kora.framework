#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use kora\lib\storage\DirectoryManager;
use kora\lib\storage\FileManager;
use Respect\Validation\Rules\Directory;

class MakeEntityCommand extends CommandCli
{

    public function __construct($path)
    {
        parent::__construct($this, $path);
    }

    public function exec(array $args)
    {
        $this->cmdArgs = array_values($args);

        $entityNameArg = OptionsCli::getArg(0,$this->cmdArgs);
        $underline = OptionsCli::getOption('--underline',$this->cmdArgs) ?? false;


        if(empty($entityNameArg))
        {
            $this->log->save('name of entity not found!',true);
        }

        $normalizedEntityName =  $this->normalizeNomenclature($entityNameArg,$underline);

        $this->app = OptionsCli::getOption('--app',$this->cmdArgs);
        $rewrite = OptionsCli::getOption('--rewrite',$this->cmdArgs) ?? false;

        if(empty($this->app))
        {
            $this->log->save("--app argument not found!",true);
        }

        $normalizedAppName =  $this->normalizeNomenclature($this->app);

        $nameFullEntity = $normalizedEntityName['normalized'].'Entity';

        $basePath = dirname(__DIR__, 6);
    
        $directoryApp = $normalizedAppName['lower']; 

        $pathEntities = $this->directoryManager->createInMemory($basePath,"app/$directoryApp/data/entities");
        $dirEntity = $this->directoryManager->createByPath($pathEntities);

        $file = new FileManager($dirEntity);

        if(!$file->exists("$nameFullEntity.php") || $rewrite)
        {
            $thisPath = dirname(__DIR__,1);
            $dirSkeleton = new DirectoryManager($thisPath,[],false,true);
            $pathSkeleton = $dirSkeleton->createInMemory($dirSkeleton->getCurrentStorage(),"skeleton");
            $base = file_get_contents("$pathSkeleton/EntitySkeleton.kora");
                $fileClass = str_ireplace(                                
                [
                    '{{__nameApp}}',
                    '{{__nameEntityFull}}',
                    '{{__nameEntity}}',
                    '{{__nameEntityLower}}',
                ],
                [
                    $normalizedAppName['lower'],
                    $nameFullEntity,
                    $normalizedEntityName['normalized'],
                    $normalizedEntityName['lower']
                ],$base);

            $file->save("$nameFullEntity.php",$fileClass);

            $this->log->save("Class {$nameFullEntity} created!");
        }
        else
        {
            $this->log->save("Class {$nameFullEntity} alredy exists!");
        }
        
        $this->log->showAllBag(false);

    }
}

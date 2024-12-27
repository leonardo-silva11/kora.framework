#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use kora\lib\storage\DirectoryManager;
use kora\lib\storage\FileManager;

class MakeInputCommand extends CommandCli
{

    public function __construct($path)
    {
        parent::__construct($this, $path);
    }

    public function exec(array $args)
    {
        $this->cmdArgs = array_values($args);

        $inputNameArg = OptionsCli::getArg(0,$this->cmdArgs);

        if(empty($inputNameArg))
        {
            $this->log->save('name of input not found!',true);
        }

        $normalizedInputName =  $this->normalizeNomenclature($inputNameArg);

        $this->app = OptionsCli::getOption('--app',$this->cmdArgs);
        $rewrite = OptionsCli::getOption('--rewrite',$this->cmdArgs) ?? false;

        if(empty($this->app))
        {
            $this->log->save("--app argument not found!",true);
        }

        $normalizedAppName =  $this->normalizeNomenclature($this->app);

        $nameFullInput = $normalizedInputName['normalized'].'Input';

        $basePath = dirname(__DIR__, 6);
    
        $directoryApp = $normalizedAppName['lower']; 

        $pathInputs = $this->directoryManager->createInMemory($basePath,"app/$directoryApp/inputs");
        $dirInput = $this->directoryManager->createByPath($pathInputs);
        
        $file = new FileManager($dirInput);

        if(!$file->exists("$nameFullInput.php") || $rewrite)
        {
            $thisPath = dirname(__DIR__,1);
            $dirSkeleton = new DirectoryManager($thisPath,[],false,true);
            $pathSkeleton = $dirSkeleton->createInMemory($dirSkeleton->getCurrentStorage(),"skeleton");
            $base = file_get_contents("$pathSkeleton/InputSkeleton.kora");
                $fileClass = str_ireplace(                                
                [
                    '{{__nameApp}}',
                    '{{__nameInputFull}}',
                ],
                [
                    $normalizedAppName['lower'],
                    $nameFullInput,
                ],$base);

            $file->save("$nameFullInput.php",$fileClass);

            $this->log->save("Class {$nameFullInput} created!");
        }
        else
        {
            $this->log->save("Class {$nameFullInput} alredy exists!");
        }
        
        $this->log->showAllBag(false);

    }
}

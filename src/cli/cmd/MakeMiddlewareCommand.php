#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use kora\lib\storage\DirectoryManager;
use kora\lib\storage\FileManager;
use Respect\Validation\Rules\Directory;

class MakeMiddlewareCommand extends CommandCli
{

    public function __construct($path)
    {
        parent::__construct($this, $path);
    }

    public function exec(array $args)
    {
        $this->cmdArgs = array_values($args);

        $middlewareNameArg = OptionsCli::getArg(0,$this->cmdArgs);

        if(empty($middlewareNameArg))
        {
            $this->log->save('name of Middleware not found!',true);
        }

        $normalizedMiddlewareName =  $this->normalizeNomenclature($middlewareNameArg);

        $this->app = OptionsCli::getOption('--app',$this->cmdArgs);
        $rewrite = OptionsCli::getOption('--rewrite',$this->cmdArgs) ?? false;

        if(empty($this->app))
        {
            $this->log->save("--app argument not found!",true);
        }
        
        $normalizedAppName =  $this->normalizeNomenclature($this->app);

        $aliasRoute = OptionsCli::getOption('--route',$this->cmdArgs);

        if(empty($aliasRoute))
        {
            $this->log->save("--route argument not found!",true);
        }

        $nameMethod = OptionsCli::getOption('--method',$this->cmdArgs) ?? 'index';
        $order = OptionsCli::getOption('--order',$this->cmdArgs) ?? 'before';

        $nameFullMiddleware = $normalizedMiddlewareName['normalized'].'Middleware';
        $basePath = dirname(__DIR__, 6);
    
        $directoryApp = $normalizedAppName['lower']; 

        $pathMiddlewares = $this->directoryManager->createInMemory($basePath,"app/$directoryApp/middlewares");
        $dirMiddleware = $this->directoryManager->createByPath($pathMiddlewares);

        $file = new FileManager($dirMiddleware);

        if(!$file->exists("$nameFullMiddleware.php") || $rewrite)
        {
            $thisPath = dirname(__DIR__,1);
            $dirSkeleton = new DirectoryManager($thisPath,[],false,true);
            $pathSkeleton = $dirSkeleton->createInMemory($dirSkeleton->getCurrentStorage(),"skeleton");
            $base = file_get_contents("$pathSkeleton/MiddlewareSkeleton.kora");
                $fileClass = str_ireplace(                                
                [
                    '{{__nameApp}}',
                    '{{__nameMiddlewareFull}}',
                    '{{__nameMethod}}'
                ],
                [
                    $normalizedAppName['lower'],
                    $nameFullMiddleware,
                    $nameMethod
                ],$base);

            if($file->save("$nameFullMiddleware.php",$fileClass))
            {
                $fullName = $normalizedMiddlewareName['normalized'];
                $constructor = "{$fullName}Constructor";
                $MakeConfig = new MakeConfig($normalizedAppName['lower']);
                $MakeConfig->addMiddleware($aliasRoute,$order,$nameFullMiddleware,[$constructor,$nameMethod],$rewrite);
                $MakeConfig->routesSave();
            }


            $this->log->save("Class {$nameFullMiddleware} created!");
        }
        else
        {
            $this->log->save("Class {$nameFullMiddleware} alredy exists!");
        }
        
        $this->log->showAllBag(false);

    }
}

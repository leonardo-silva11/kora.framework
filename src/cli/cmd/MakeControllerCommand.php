#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use Symfony\Component\Filesystem\Filesystem;


class MakeControllerCommand extends CommandCli
{
    public function __construct(string $path)
    {
        parent::__construct($this,$path);
    }

    public function exec()
    {
        $basePath = dirname(__DIR__, 1);
        $baseFile = file_get_contents("$basePath/skeleton/ControllerSkeleton.kora");
        $this->createController($baseFile);
    }

    public function createController($baseFile)
    {
        $nameController = ucfirst($this->cmdArgs[0]);
    
        $forceOverwrite =  OptionsCli::getOption('-f',$this->cmdArgs) != null;
        $method = OptionsCli::getOption('--m',$this->cmdArgs);
        $method = empty($method) ? 'index' : $this->getAndValidate($method,'method');
        $params = '';
  
        $baseFile = str_ireplace([
            '{{appName}}',
            '{{nameController}}',
            '{{nameMethod}}',
            '{{params}}'
        ],[
           $this->app['lowerName'],
           $nameController,
           $method,
           $params
        ],[$baseFile]);

        $this->save($this->paths['defaultDirectoryControllerPath'],"{$nameController}Controller",$baseFile,$forceOverwrite);

        $this->log->save("Controller {$nameController} created sucessfully for app {$this->app['lowerName']}!",true);
    }
}

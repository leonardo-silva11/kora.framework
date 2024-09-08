<?php
namespace kora\cli\cmd;

use kora\lib\exceptions\DefaultException;
use kora\lib\storage\DirectoryManager;
use kora\lib\support\Log;
use stdClass;

abstract class CommandCli
{
    protected $directorySeparator = DIRECTORY_SEPARATOR;
    protected array $cmdArgs = [];
    protected array $paths = [];
    protected array $settings = [];
    protected array $dbConfig = [];
    protected $app;
    protected CommandCli $Command;
    protected Log $log;
    protected DirectoryManager $directoryManager;

    abstract function exec(array $arg);

    protected function __construct(CommandCli $Command,string $path)
    {
        $this->Command = $Command;
        $this->paths['project'] = $path; 
        $this->directoryManager = new DirectoryManager($Command::class);
        $this->log = new Log($this->directoryManager->cloneStorage());
    }

    private function appConfig(int $appKey)
    {
        $appKey -= 1;
   
        $i = 0;

        foreach($this->settings['apps'] as $key => $app)
        {
            if($appKey == $i)
            {
                $this->app['name'] = $key;
                $this->app['lowerName'] = mb_strtolower($key);
                $this->app['attrs'] = $app;
                break;
            }

            ++$i;
        }

        if(empty($this->app))
        {
            $this->log->save("The app in position: {$appKey} not found in appsettings.json!",true);
        }
    }

    private function getDatabaseConfig(int $appKey, int $appConn)
    {
  
        if(!array_key_exists('connectionStrings',$this->app['attrs']))
        {
            $this->log->save("connectionStrings key not found in settings app {$this->app['name']}!",true);
        }

        $connectionStrings = array_values($this->app['attrs']['connectionStrings']);

        $key = $appConn - 1;

        if(!array_key_exists($key,$connectionStrings))
        {
            $this->log->save("The database configuration in position: {$appKey} not found for app {$this->app['name']}!",true);
        }

        $configDatabase = $connectionStrings[$key];

        return $configDatabase;
    }

    private function createDirectories()
    {
        foreach($this->paths as $path)
        {
            if(!is_dir($path))
            {
                mkdir($path,0774,true);
            }
        }
    }

    protected function saveFile($path,$data,$overwrite = false)
    {
 
        if(!file_exists($path) || $overwrite)
        {
            file_put_contents($path,$data);
        }
    }

    protected function save($path, $name, $data, $forceOverwrite)
    {
        $pathFile = "{$path}{$this->directorySeparator}{$name}.php";

        if(!file_exists($pathFile) || $forceOverwrite)
        {
            file_put_contents($pathFile, $data);
            return $pathFile;
        }

        $this->log->save("File {{$name}}.php exists in {{$path}}!",true);
    }

    protected function getAndValidate(string $value, string $resourceName)
    {
        if(!preg_match('/^[A-Za-z_]/',$value))
        {
            $this->log->save("The name of the {$resourceName} is invalid!",true);
        }

        return $value;
    }

    private function validateCommand($appKey, $appConn)
    {
        if($this->Command::class == 'kora\\cli\\cmd\\MakeControllerCommand')
        {
            if(count($this->cmdArgs) < 1)
            {
                $this->log->save('Make Controller expected at least one argument zero found!',true);
            }
            else if(!preg_match('/^[A-Za-z_]/',$this->cmdArgs[0]))
            {
                $this->log->save('Invalid command, the first argument must be the name of the controller!',true);
            }
            
            $this->paths = 
            [
                'defaultDirectoryControllerPath' => "{$this->paths['app']}{$this->app['lowerName']}{$this->directorySeparator}controllers",
            ];

            $this->createDirectories();
        }
        else if($this->Command::class == 'kora\\cli\\cmd\\ORM')
        {
            $this->dbConfig = $this->getDatabaseConfig($appKey,$appConn);

            $this->paths = 
            [
                'defaultDirectoryPath' => "{$this->paths['app']}{$this->app['lowerName']}{$this->directorySeparator}models{$this->directorySeparator}database",
                'defaultDirectoryEntityPath' => "{$this->paths['app']}{$this->app['lowerName']}{$this->directorySeparator}models{$this->directorySeparator}database{$this->directorySeparator}entity",
                'defaultDirectoryMigrationsPath' => "{$this->paths['app']}{$this->app['lowerName']}{$this->directorySeparator}models{$this->directorySeparator}database{$this->directorySeparator}migrations",
                'entity' => "{$this->paths['app']}{$this->app['lowerName']}{$this->directorySeparator}models{$this->directorySeparator}database{$this->directorySeparator}entity",
                'migrations' => "{$this->paths['app']}{$this->app['lowerName']}{$this->directorySeparator}models{$this->directorySeparator}database{$this->directorySeparator}migrations",
            ];

            $this->createDirectories();
        }
        else if($this->Command::class == 'kora\\cli\\cmd\\MakeRouteCommand')
        {
    
            if(count($this->cmdArgs) < 2)
            {
                $this->log->save('Make Route expected at least two arguments {--c} and {--a}!',true);
            }
            
            $this->paths = 
            [
                'routeJsonFile' => "{$this->paths['app']}{$this->app['lowerName']}{$this->directorySeparator}route.json",
            ];
        }
    }

   


    public function config(array $args, string $type)
    {     
        try 
        {
            $type = ucfirst($type);

            $this->cmdArgs = array_values($args);


            $this->paths['app'] = "{$this->paths['project']}{$this->directorySeparator}app{$this->directorySeparator}";


            $pathSettings = file_exists("{$this->paths['project']}{$this->directorySeparator}soundconfig.json")
                                 ? "{$this->paths['project']}{$this->directorySeparator}soundconfig.json"
                                 : (file_exists("{$this->paths['project']}{$this->directorySeparator}appsettings.json") 
                                      ? "{$this->paths['project']}{$this->directorySeparator}appsettings.json"
                                      : throw new DefaultException('File {appsettings.json} not found, impossible load database configuration!',500));
                          
            $file = json_decode(file_get_contents($pathSettings),true);
    
            if($file == null)
            {
                throw new DefaultException('Invalid file to access: {appsettings.json}!',500);
            }

           $this->settings = array_key_exists('appsettings',$file)
                              &&
                              !empty($file['appsettings']['path'])  
                              &&
                              file_exists($file['appsettings']['path'])
                              ?
                              json_decode(file_get_contents($file['appsettings']['path']),true)
                              :
                              $file;

            $appKey = OptionsCli::getOption('--app',$args) ?? 1;
            $appConn = OptionsCli::getOption('--conn',$args) ?? 1;
            $appKey = (int)$appKey;
            $appConn = (int)$appConn;

            $this->appConfig($appKey);
            $this->validateCommand($appKey, $appConn);
          
        } 
        catch (\Throwable $th) 
        {
           $this->log->save($th->getMessage(),true);
        }   
    }
}
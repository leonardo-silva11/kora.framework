<?php
namespace kora\lib\storage;

use Exception;
use kora\lib\exceptions\DefaultException;
use kora\lib\strings\Strings;
use kora\lib\support\OperationSystem;

class DirectoryManager
{
    private string $currentDrive;
    private string $storage = "phpDefaultStorageKora";
    private $currentStorage;
    private $defaultDrives = 
    [
        "C:\\",
        "/var"
    ];

    public function __construct(string $storage = null, array $defaultDrives =[]) 
    {
        $this->defaultDrives = !empty($defaultDrives) ? $defaultDrives : $this->defaultDrives;
        $this->storage = $storage ?? $this->storage;
        $this->defaultDrive(); 
        $this->loadStorage();      
    }

    private function defaultDrive() : void
    {
        $drives = glob('/*', GLOB_ONLYDIR);

        $this->currentDrive = Strings::empty;

        $drive = array_intersect($this->defaultDrives, $drives);

        if(!empty($drive))
        {
            $drive = end($drive);
        }

        if(empty($drive))
        {
            $os = OperationSystem::getOS();
            $currentOS = !empty($os) ? $os['acronym'] : 'unknow';
            throw new Exception("The drive not found in OS {$currentOS}!",404);
        }
       
        $this->currentDrive = $drive;
    }

    private function saveDirectory($path)
    {
 
        if(!is_dir($path))
        {      
            mkdir($path,0770);
            chmod($path,0770);
        }

        $this->currentStorage = $path;
    }

    public function back()
    {
        $path = dirname($this->getCurrentStorage());
        $this->currentStorage = $path;
    }

    public function isCurrentStorage(string $directory)
    {
        $segments = explode($this->getDirectorySeparator(),$this->getCurrentStorage());
        $last = end($segments);

        return !empty($directory) && $last === $directory;
    }

    public function forward(string $directory): bool
    {
        $cd = false;

        if($this->directoryExists($directory))
        {
            $this->currentStorage .= "{$this->getDirectorySeparator()}{$directory}";
            $cd = true;
        }

        return $cd;
    }

    public function createOrForward($directory) : mixed
    {
        if
            (
                !$this->isCurrentStorage($directory) 
                && 
                !$this->directoryExists($directory))
        {
            return $this->saveDirectory("{$this->getCurrentStorage()}{$this->getDirectorySeparator()}{$directory}");
        }
        else if($this->directoryExists($directory))
        {
            return $this->forward($directory);
        }

        return false;
    }

    private function loadStorage()
    {
        $path = $this->getCurrentStorage();
        $this->saveDirectory($path);
    }

    public function getDrive()
    {
        return $this->currentDrive;
    }

    public static function getDirectorySeparator()
    {
        return DIRECTORY_SEPARATOR;
    }

    public function directoryExists(string $directoryName) : bool
    {
        if(empty($directoryName))
        {
            throw new DefaultException("directory name is empty!",403);
        }

        return is_dir("{$this->getCurrentStorage()}{$this->getDirectorySeparator()}{$directoryName}");
    }

    public function newStorage(string $directory)
    {
        $directory = $directory ?? new DefaultException("Diretório inválido!",403);

        $basePathArray = explode($this->getDirectorySeparator(),$this->getCurrentStorage());
        $basePathArray = array_values(array_filter($basePathArray));
        unset($basePathArray[0]);
        $basePathArray = array_values($basePathArray);
        $basePath = implode($this->getDirectorySeparator(),$basePathArray);

        return new DirectoryManager("{$basePath}{$this->getDirectorySeparator()}{$directory}");
    }

    public function getCurrentStorage()
    {
        return  !empty($this->currentStorage) ? $this->currentStorage : "{$this->currentDrive}{$this->getDirectorySeparator()}{$this->storage}";
    }

    public function cloneStorage()
    {
        return new DirectoryManager($this->storage,$this->defaultDrives);
    }
}

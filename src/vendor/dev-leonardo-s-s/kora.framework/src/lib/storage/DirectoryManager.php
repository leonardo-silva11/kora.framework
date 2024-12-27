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
    private bool $readOnly = false;
    private string $inMemory;
    private $defaultDrives = 
    [
        "C:\\",
        "/var"
    ];

    public function __construct(string $storage = null, array $defaultDrives =[], bool $readOnly = false, bool $createPath = false) 
    {
        $this->defaultDrives = !empty($defaultDrives) ? $defaultDrives : $this->defaultDrives;
        $this->storage = $storage ?? $this->storage;
        $this->readOnly = $readOnly;

        if(!$createPath)
        {
            $this->defaultDrive(); 
            $this->loadStorage();  
        }
        else
        {
            $this->_createByPath($storage);
        }
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

            mkdir($path,0770,true);
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

        if($this->directoryExists($directory) && !$this->readOnly)
        {
            $this->currentStorage .= "{$this->getDirectorySeparator()}{$directory}";
            $cd = true;
        }

        return $cd;
    }

    public function createInMemory($path,$directory)
    {
        $this->inMemory = $this->normalizePath("$path/$directory");
        return $this->inMemory;
    }

    public function getInMemory()
    {
        return $this->inMemory;
    }

    private function _createByPath($path)
    {
        $p = explode($this->getDirectorySeparator(),$path);
        $first = $p[0];
        $this->currentDrive = empty($first) ? "/$p[1]" : $p[0];
        unset($p[0]);
        
        if(empty($first)){  unset($p[1]); };

        $this->storage = implode($this->getDirectorySeparator(),$p);

        $this->loadStorage();
    }

    public function createByPath(string $path)
    {
        return new DirectoryManager($path,[],false,true);
    }

    public function createOrForward($directory) : mixed
    {
        if
            (
                !$this->readOnly
                &&
                !$this->isCurrentStorage($directory) 
                && 
                !$this->directoryExists($directory))
        {
            return $this->saveDirectory($this->normalizePath("{$this->getCurrentStorage()}{$this->getDirectorySeparator()}{$directory}"));
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

    private function normalizePath(string $path) : string
    {
        if(!empty($path))
        {
            $path = str_ireplace(["\\","/"],$this->getDirectorySeparator(),$path);
        }

        return $path;
    }

    public function directoryExists(string $directoryName) : bool
    {
        if(empty($directoryName))
        {
            throw new DefaultException("directory name is empty!",403);
        }

        return is_dir($this->normalizePath("{$this->getCurrentStorage()}{$this->getDirectorySeparator()}{$directoryName}"));
    }

    public function directoryExistsByFullPath(string $path) : bool
    {
        if(empty($path))
        {
            throw new DefaultException("path is null or empty!",403);
        }

        return is_dir($path);
    }

    public function newStorage(string $directory)
    {
        $directory = $directory ?? new DefaultException("Diretório inválido!",403);

        $basePathArray = explode($this->getDirectorySeparator(),$this->getCurrentStorage());
        $basePathArray = array_values(array_filter($basePathArray));
        $basePathArray = array_values($basePathArray);
        $basePath = implode($this->getDirectorySeparator(),$basePathArray);
        return new DirectoryManager("{$basePath}{$this->getDirectorySeparator()}{$directory}");
    }

    public function getCurrentStorage()
    {
        $p  =  !empty($this->currentStorage) ? $this->currentStorage : "{$this->currentDrive}{$this->getDirectorySeparator()}{$this->storage}";

        return $this->normalizePath($p);
    }

    public function cloneStorage()
    {
        return new DirectoryManager($this->storage,$this->defaultDrives);
    }
}

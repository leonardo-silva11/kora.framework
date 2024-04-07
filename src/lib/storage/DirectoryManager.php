<?php
namespace kora\lib\storage;

use Exception;
use kora\lib\strings\Strings;
use kora\lib\support\OperationSystem;

class DirectoryManager
{
    private string $currentDrive;
    private string $storage = "phpDefaultStorageKora";
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

    private function loadStorage()
    {
        $path = $this->getCurrentStorage();

        if(!is_dir($path))
        {
            mkdir($path,0770);
            chmod($path,0770);
        }
    }

    public function getDrive()
    {
        return $this->currentDrive;
    }

    public static function getDirectorySeparator()
    {
        return DIRECTORY_SEPARATOR;
    }

    public function getCurrentStorage()
    {
        return "{$this->currentDrive}{$this->getDirectorySeparator()}{$this->storage}";
    }

}

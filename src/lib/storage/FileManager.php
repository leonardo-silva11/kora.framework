<?php
namespace kora\lib\storage;

use Exception;
use kora\lib\exceptions\DefaultException;
use kora\lib\strings\Strings;
use kora\lib\support\OperationSystem;

class FileManager
{
    public DirectoryManager $Storage;

    public function __construct(DirectoryManager $Storage)
    {
        $this->Storage = $Storage;
    }

    public function exists(string $fileName) : bool
    {
        if(empty($fileName))
        {
            throw new DefaultException("file name is empty!",403);
        }

        return file_exists("{$this->Storage->getCurrentStorage()}{$this->Storage->getDirectorySeparator()}{$fileName}");
    }

    private function getNewPathFile(string $name): string
    {
        return "{$this->Storage->getCurrentStorage()}{$this->Storage->getDirectorySeparator()}{$name}";
    }

    public function read(string $name)
    {
        $file = null;

        if($this->exists($name))
        {
            $pathFile = $this->getNewPathFile($name);
           
            $file = file_get_contents($pathFile);

        }

        return $file;
    }

    public function save(string $name, mixed $data, bool $rewrite = true)
    {

        if($rewrite || (!$rewrite && !$this->exists($name)))
        {
            return file_put_contents($this->getNewPathFile($name),$data);
        }

        return false;
    }
}
<?php
namespace kora\lib\storage;

use Exception;
use kora\lib\exceptions\DefaultException;
use kora\lib\strings\Strings;
use kora\lib\support\OperationSystem;

class FileManager
{
    private DirectoryManager $Storage;

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
}
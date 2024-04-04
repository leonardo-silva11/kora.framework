<?php
namespace kora\lib\support;

use DateTime;
use Throwable;
use kora\lib\exceptions\DefaultException;
use kora\lib\storage\DirectoryManager;


class Log
{
    private DirectoryManager $directoryManager;

    public function __construct(DirectoryManager $directoryManager)
    {
        $this->directoryManager = $directoryManager;
    }

    public function save(string $message, $showMessage = false) 
    {
        $fileName = sprintf("%s.%s",(new DateTime())->format('Y-m-d.H'),"txt");
        $dateNow = (new DateTime())->format('d/m/Y H:i:s');

        $pathLog = $this->directoryManager->getCurrentStorage();
        $pathFile = "{$pathLog}{$this->directoryManager->getDirectorySeparator()}{$fileName}";

        try
        {
            $msg = "{$dateNow} - {$message}".PHP_EOL;

            $file = fopen($pathFile, 'a');

            fwrite($file,$msg);

            fclose($file);

            if($showMessage)
            {
                exit(printf($msg));
            }
        }
        catch (Throwable $th)
        {
            throw $th;
        }
    }
}

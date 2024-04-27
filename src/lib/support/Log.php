<?php
namespace kora\lib\support;

use DateTime;
use Throwable;
use kora\lib\storage\DirectoryManager;
use kora\lib\strings\Strings;

class Log
{
    private DirectoryManager $directoryManager;
    private array $bagMessages = [];
    private bool $useBag = true;

    public function __construct(DirectoryManager $directoryManager)
    {
        $this->directoryManager = $directoryManager;

        return $this;
    }

    public function useBag(bool $useBag)
    {
        $this->useBag = $useBag;
    }

    public function clearBag()
    {
        $this->bagMessages = [];

        return $this;
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

            if($this->useBag)
            {
                array_push($this->bagMessages,$msg);
            }

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

    public function showAllBag($exit = true)
    {
        $msgs = Strings::empty;

        foreach($this->bagMessages as $msg)
        {
            $msgs .= $msg;
        }
      
        printf($msg);

        if($exit){ exit; }
    }
}

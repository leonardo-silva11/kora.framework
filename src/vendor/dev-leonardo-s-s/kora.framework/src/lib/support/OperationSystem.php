<?php
namespace kora\lib\support;

class OperationSystem
{
    private static $OSs = [
       'WINNT' => ['name' => 'Windows', 'acronym' => 'win'],
       'WIN32' => ['name' => 'Windows', 'acronym' => 'win'],
       'Windows' => ['name' => 'Windows', 'acronym' => 'win'],
       'Linux' => ['name' => 'Linux', 'acronym' => 'lin'],
       'Darwin' => ['name' => 'Mac OS X', 'acronym' => 'mac']
    ];

    public static function getOS()
    {
        return self::$OSs[PHP_OS] ?? [];
    }
}
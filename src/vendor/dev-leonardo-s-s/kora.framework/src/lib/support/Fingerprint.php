<?php
namespace kora\lib\support;

use kora\lib\exceptions\DefaultException;
use Symfony\Component\HttpFoundation\Request;

class Fingerprint
{
    private Request $Request;
    private array $dataForFingerPrint = [];
    private array $required = 
    [
        'host',
        'connection',
        'user-agent',
        'accept',
        'accept-language',
        'accept-encoding'
    ];

    private array $filtereds = 
    [
        'content-type',
        'postman-token',
        'cookie'
    ];
    
    public function __construct(Request $Request)
    {
        $this->Request = $Request;
    }

    public function setKeyToFilter(string $key)
    {
        if(!empty($key) && !in_array($key,$this->filtereds))
        {
            array_push($this->filtereds,mb_strtolower($key));
        }
    }

    public function getFiltereds()
    {
        return $this->filtereds;
    }

    public function generate()
    {
        $requiredHeaders = $this->filterKeys();

        $diff = array_diff($this->required,$requiredHeaders);

        if(!empty($diff))
        {
            throw new DefaultException('For security purposes, some headers are required in the request and one or more headers are missing.',404);
        }

        return !empty($this->dataForFingerPrint) 
        ? hash('sha256',json_encode($this->dataForFingerPrint)) 
        : throw new DefaultException('It is impossible to determine the customer who made the request.',400);
    }

    private function filterKeys() : array
    {
        $headers = $this->Request->headers->all();

        $required = [];
        foreach($headers as $key => $value)
        {
            $key = mb_strtolower($key);

            if(in_array($key,$this->required) && !empty($value))
            {
                
                array_push($required,$key);
            }

            if(!in_array($key,$this->filtereds))
            {
                $this->dataForFingerPrint[$key] = $value;
            }
        }

        return $required;
    }

}
<?php
namespace kora\bin;

use kora\lib\exceptions\DefaultException;

class FilterResponseKora
{
    private array $response = [
        'before' => null,
        'after' => null
    ];

    private string $typeFilter;

    public function __construct($typeFilter, array $response)
    {
        if(!array_key_exists($typeFilter,$this->response))
        {
            throw new DefaultException("Type filter {$typeFilter} is no allowed!",400);
        }

        $this->response[$typeFilter] = $response;
        $this->typeFilter = $typeFilter;
    }

    public function getReponse($key = null)
    {
        return array_key_exists($key,$this->response[$this->typeFilter]) ? $this->response[$this->typeFilter][$key] : $this->response[$this->typeFilter];
    }

    public function __getShortName()
    {
        return (new \ReflectionClass($this))->getShortName();
    }
}
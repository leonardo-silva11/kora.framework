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
    private string $name;

    public function __construct(string $typeFilter, array $response)
    {
        if(!array_key_exists($typeFilter,$this->response))
        {
            throw new DefaultException("Type filter {$typeFilter} is no allowed!",400);
        }

        $this->response[$typeFilter] = $response;
        $this->typeFilter = $typeFilter;

        $method = key($response);
        $sufix =  ucfirst($this->typeFilter);
        $this->name = "{$method}{$sufix}";

        return $this;
    }

    public function getReponse($key = null)
    {
        return array_key_exists($key,$this->response[$this->typeFilter]) ? $this->response[$this->typeFilter][$key] : $this->response[$this->typeFilter];
    }

    public function getName()
    {
        return $this->name;
    }
}
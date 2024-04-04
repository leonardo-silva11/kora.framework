<?php
namespace kora\bin;

use kora\lib\exceptions\DefaultException;

class IntermediatorResponseKora
{
    private array $response;
    public function __construct(array $data, array $config, FilterResponseKora $filterResponseKoraAfter)
    {
        $this->response =  [
            'data' => $data,
            'config' => $config,
            'filter' => $filterResponseKoraAfter->getReponse()
        ];
    }

    public function getReponse($key)
    {
        return array_key_exists($key,$this->response) ? $this->response[$key] : throw new DefaultException("{$key} is not a valid key for reponse intermediator!",400);
    }

}
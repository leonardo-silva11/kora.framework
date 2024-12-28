<?php
namespace kora\bin;

use kora\lib\exceptions\DefaultException;

class MiddlewareResponseKora
{
    private array $response = [
        'before' => null,
        'after' => null
    ];

    private string $typeMiddleware;
    private string $name;

    public function __construct(string $typeMiddleware, array $response)
    {
        if(!array_key_exists($typeMiddleware,$this->response))
        {
            throw new DefaultException("Type middleware {$typeMiddleware} is no allowed!",400);
        }

        $this->response[$typeMiddleware] = $response;
        $this->typeMiddleware = $typeMiddleware;

        $method = key($response);
        $sufix =  ucfirst($this->typeMiddleware);
        $this->name = "{$method}{$sufix}";

        return $this;
    }

    public function getResponse($key = null)
    {
        return array_key_exists($key,$this->response[$this->typeMiddleware]) ? $this->response[$this->typeMiddleware][$key] : $this->response[$this->typeMiddleware];
    }

    public function getByPath($key = null)
    {
        $keys = explode('.',$key);

        $i = 0;

        $result = $this->response[$this->typeMiddleware];

        while($i < count($keys))
        {
            if((is_array($result) && (empty($keys[$i]) || !array_key_exists($keys[$i],$result))))
            {
                throw new DefaultException("The path: {{$key}} not found in bag!",404);
            }
            
            $result = $result[$keys[$i]];

            if(is_object($result) && method_exists($result,'getResponse'))
            {
                $result = $result->getResponse();
            }
            
            ++$i;
        }

        return $result;
    }

    public function getName()
    {
        return $this->name;
    }
}
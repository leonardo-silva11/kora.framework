<?php
namespace kora\bin;

use kora\lib\exceptions\DefaultException;

class BagKora
{
    private array $bag;
    private string $name = "GenericBag";

    public function __construct($name)
    {
        $this->name = !empty($name) ? $name : $this->name;
        $this->bag = [];
    }

    public function add(string $key, mixed $item)
    {
        if(!array_key_exists($key,$this->bag))
        {
            $this->bag[$key] = $item;
        }
    }

    public function getResponse(string $key)
    {
        $keys = explode('.',$key);

        $i = 0;

        $result = $this->bag;

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

    public function all()
    {
        return $this->bag;
    }

    public function getName()
    {
        return $this->name;
    }
}
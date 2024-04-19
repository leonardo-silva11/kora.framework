<?php
namespace kora\bin;

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
        return array_key_exists($key,$this->bag) ? $this->bag[$key] : null;
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
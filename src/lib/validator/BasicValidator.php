<?php

namespace kora\lib\validator;

use kora\lib\exceptions\DefaultException;

class BasicValidator
{
    public static function keyExistsAndIsNotEmptyValue($key, Array $data, $msgException = null)
    {
        $s = (array_key_exists($key,$data) && !is_bool($data[$key]) && !empty($data[$key])) 
        || (array_key_exists($key,$data) && is_bool($data[$key])); 

        if(!$s && !empty($msgException))
        {
            throw new DefaultException($msgException,400);
        }

        return $s;
    }
}
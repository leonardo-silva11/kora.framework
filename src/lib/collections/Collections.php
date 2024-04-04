<?php
namespace kora\lib\collections;

use kora\lib\strings\Strings;

class Collections
{

    public static function searchInDepthArray($key,Array $collection, Array &$collectionRef = null)
    {
        $search = null;

        if(!empty($key))
        {
  
            $collection = !empty($collection) ? $collection : $collectionRef;

            $kParts = explode('.',$key);

            if(array_key_exists($kParts[0],$collection))
            {
                $search = $collection[$kParts[0]];
                $l = count($kParts);
                $i = 1;
  
                while($i < $l  && array_key_exists($kParts[$i],$search))
                {
                    $search = $search[$kParts[$i]];
                    ++$i;
                }


                if($i != $l)
                {
                    $search = null;
                }
            }
        }
        
        return $search;
    }


    public static function searchInDepthArrayCollection($key,Array $collection = [], Array &$collectionRef = [])
    {
        $search = ['value' => null, 'segmentParts' => [],'pathKey' => Strings::empty];

        if(!empty($key))
        {
    
            $collection = !empty($collection) ? $collection : $collectionRef;
            $search['segmentParts'] = explode('.',$key);

            if(array_key_exists($search['segmentParts'][0],$collection))
            {
                $search['value'] = $collection[$search['segmentParts'][0]];
                $search['pathKey'] = $search['segmentParts'][0];
        
                $l = count($search['segmentParts']);
                $i = 1;
         
                while($i < $l  && array_key_exists($search['segmentParts'][$i],$search['value']))
                {
                 
                    $search['value'] = $search['value'][$search['segmentParts'][$i]];
                    $search['pathKey'] .= ".{$search['segmentParts'][$i]}";

                    ++$i;
                }

                if($i != $l)
                {
                    $search = null;
                }

   
            }
        }
       
        return $search;
    }

    public static function addElementInFirstPositionArray($key, $val, &$collection) 
    {
        $collection =[$key => $val] + $collection;
    }
    


    public static function getElementArrayKeyInsensitive(string $key,array $array, array &$arrayRef = null) : array
    {
            $array = !empty($array) ? $array : $arrayRef;

            $search = ['key' => null, 'element' => null];
            $lowerKey = strtolower($key);

            foreach ($array as $arrayKey => $value) 
            {
                if (strtolower($arrayKey) === $lowerKey) 
                {
                    $search['key'] = $arrayKey;
                    $search['element'] = $value;
                    break;
                }
            }

            return $search;
    }


    public static function arrayKeyExistsInsensitive(string $key,array $array)
    {

        $copyArray = [...$array];
        $lowerKey = strtolower($key);
        $lowerKeys = array_map('strtolower', array_keys($copyArray));

        return in_array($lowerKey, $lowerKeys);
    }

    public static function lowerAllKeysArray(&$array)
    {
        $array =  array_change_key_case($array, CASE_LOWER);

        return $array;
    }
}

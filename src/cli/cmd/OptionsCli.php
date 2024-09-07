<?php
namespace kora\cli\cmd;

class OptionsCli
{
    public static function getOptions(array $options, $args)
    {
        $val = null;

        for($i = 0; $i < count($options); ++$i)
        {
            $val = self::getOption($options[$i],$args);

            if(!empty($val))
            {
                break;
            }
        }

        return $val;
    }

    public static function getOption($option,$args)
    {
        $result = array_filter($args, function($value)  use ($option)  
        {
            $opt1 = $option;
            $opt2 = $option;

            if(substr($option,0,2) == '--')
            {
                $opt2 = '-'.substr($option,2,1);
            }

            return explode('=',$value)[0] === $opt1 || explode('=',$value)[0] === $opt2;
        });

        $val = null;

        if(!empty($result))
        {
            $exp = explode('=',end($result));
            $val = array_key_exists(1,$exp) ? $exp[1] : $exp[0];
        }

        return $val;
    }

    public static function getArg(int $key,array $args)
    {
        $val = null;

        if(is_int($key))
        {
            $val = array_key_exists($key,$args) ? $args[$key] : $val;
        }

        return $val;
    }

}
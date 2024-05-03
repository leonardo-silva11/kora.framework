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
            return explode('=',$value)[0] === $option;
        });

        $val = null;

        if(!empty($result))
        {
            $exp = explode('=',end($result));
            $val = array_key_exists(1,$exp) ? $exp[1] : $exp[0];
        }

        return $val;
    }

}
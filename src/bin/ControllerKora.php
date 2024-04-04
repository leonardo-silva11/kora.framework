<?php
namespace kora\bin;

abstract class ControllerKora
{
    protected static AppKora $app;

    protected function __construct(){}
    
    protected function clearBuffer()
    {
        while(ob_get_length() > 0) { ob_clean(); }
    }

    protected function clearHeaders()
    {
        if(!headers_sent()){ header_remove();}
    }

    protected function whenToRedirect()
    {
        $this->clearHeaders();
        $this->clearBuffer();
    }

    protected function redirect($url)
    {
        $this->whenToRedirect();
        exit(header('location:'.$url));
    }

    protected function getParamConfig($key)
    {
        return self::$app->getParamConfig($key,'public');
    }
    
    private static function start(AppKora $app)
    {
        ControllerKora::$app = $app;
    }    
}

<?php
namespace kora\bin;

abstract class ControllerKora
{
    protected static AppKora $app;
    
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
    
    private static function inject(AppKora $app)
    {
        ControllerKora::$app = $app;
    }    
}

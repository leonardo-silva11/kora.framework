<?php 
namespace kora\bin;

abstract class AppKora
{
    private static $apps = [];

    protected function __construct(AppKora $app)
    {
         $nameApp = get_class($app);

         if(!array_key_exists($nameApp,AppKora::$apps))
         {
                AppKora::$apps[$nameApp] = $app;
         }
    }
}
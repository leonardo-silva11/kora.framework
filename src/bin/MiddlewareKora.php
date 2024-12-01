<?php
namespace kora\bin;

use kora\lib\collections\Collections;
use kora\lib\exceptions\DefaultException;


class MiddlewareKora
{
    private AppKora $app;
    
    public function __construct(array $config)
    {
        $this->app = $config['app']['instance'];
        $this->parsemiddlewares($config);

        return $this;
    }

    private function parsemiddlewares(array $config)
    {
        $middlewares = $this->app->getParamConfig('http.action.middlewares');
        $nameOfApp = $this->app->getParamConfig('app.name');
        $appPath = $this->app->getParamConfig('app.path');

        $MiddlewareCollection = 
        [
            'before' => [],
            'after' => []
        ];
     
        foreach($middlewares as $k => $f)
        {
            if(($k === 'after' || $k === 'before') && !empty($f))
            {
                foreach($f as $k2 => $f2)
                {
                    $className = $k2;
                    $namespace = "app\\$nameOfApp\\middlewares\\$className";

                    $path = $appPath.DIRECTORY_SEPARATOR.'middlewares'.DIRECTORY_SEPARATOR.$className.'.php';

                    if(!file_exists($path) || !trait_exists($namespace))
                    {
                        throw new DefaultException("Middleware {$className} does not exists!",404);
                    }

                    foreach($f2['methods'] as $mtd)
                    {
                        if(!method_exists($namespace,$mtd))
                        {
                            throw new DefaultException("Middleware {$className}/{$mtd} not found!",404);
                        }
                    }

                    array_push($MiddlewareCollection[$k],
                    [
                        'order' => $k,
                        'class' => $className,
                        'methods' => $f2['methods'],
                        'namespace' => $namespace,
                        'path' => $path
                    ]);
                }
            }
        }
        

        $this->app->setParamConfig('http.middlewares',$MiddlewareCollection,'protected');

        return $this;
    }

    public function callSingleMiddleware(ControllerKora $instance, Array $Middleware, string $method, Array $services, String $type)
    {
       
        if(!in_array($type,['before','after']))
        {
            throw new DefaultException("allowed type middlewares are: (before) and (after)!",400);
        }

        if(!in_array($Middleware['namespace'], class_uses($instance)))
        {
            throw new DefaultException(sprintf("Controller {%s} does not contains definition for {%s} Middleware!",$instance::class,$Middleware['class']),404);
        }


        $responseMiddleware = [];

        if(in_array($method,$Middleware['methods']))
        {
            $params = [];
    
            if(Collections::arrayKeyExistsInsensitive($type,$services)
                &&
            Collections::arrayKeyExistsInsensitive($method,$services[$type])
            )
            {
                $params = $services[$type][$method];
            }

            try 
            {
                 $resp = $instance->$method(...$params);

                 $responseMiddleware[$method] = $resp;
            } 
            catch (\Throwable $th) 
            {
                throw new DefaultException(sprintf("{%s} ocurred when call Middleware {%s}::{%s}!",$th->getMessage(),$instance::class, $method),500);
            }
    
        }
    
        return new MiddlewareResponseKora($type,$responseMiddleware);
    }

    public function callMiddleware(ControllerKora $instance, Array $middlewares, Array $services, String $type)
    {
        if(!in_array($type,['before','after']))
        {
            throw new DefaultException("allowed type middlewares are: (before) and (after)!",400);
        }

        $responseMiddleware = [];
        
        if(!array_key_exists($type,$middlewares))
        {
            throw new DefaultException("Type Middleware {$type} is no allowed!",400);
        }

        $callmiddlewares = $middlewares[$type];

        foreach($callmiddlewares as $k => $Middleware)
        {
            if(!in_array($Middleware['namespace'], class_uses($instance)))
            {
                throw new DefaultException(sprintf("Controller {%s} does not contains definition for {%s} Middleware!",$instance::class,$Middleware['class']),404);
            }
     
            foreach($Middleware['methods'] as $mtd)
            {
                $params = [];

                if(array_key_exists($mtd,$services[$type]))
                {
                    $params = $services[$type][$mtd];
                }
               
                try 
                {
                     $responseMiddleware[$mtd] = $instance->$mtd(...$params);
                } 
                catch (\Throwable $th) 
                {
                    throw new DefaultException(sprintf("{%s} ocurred when call Middleware {%s}::{%s}!",$th->getMessage(),$instance::class, $mtd),500);
                }
     
            }
        }

        return new MiddlewareResponseKora($type,$responseMiddleware);
    }
}
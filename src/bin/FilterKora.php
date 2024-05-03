<?php
namespace kora\bin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use JmesPath\Env;
use kora\lib\collections\Collections;
use kora\lib\exceptions\DefaultException;
use kora\lib\strings\Strings;

class FilterKora
{
    private AppKora $app;
    
    public function __construct(array $config)
    {
        $this->app = $config['app']['instance'];
        $this->parseFilters($config);

        return $this;
    }

    private function parseFilters(array $config)
    {
        $filters = $this->app->getParamConfig('http.action.filters');
        $nameOfApp = $this->app->getParamConfig('app.name');
        $appPath = $this->app->getParamConfig('app.path');

        $filterCollection = 
        [
            'before' => [],
            'after' => []
        ];
     
        foreach($filters as $k => $f)
        {
            if(($k === 'after' || $k === 'before') && !empty($f))
            {
                foreach($f as $k2 => $f2)
                {
                    $className = $k2;
                    $namespace = "app\\$nameOfApp\\filters\\$className";

                    $path = $appPath.DIRECTORY_SEPARATOR.'filters'.DIRECTORY_SEPARATOR.$className.'.php';

                    if(!file_exists($path) || !trait_exists($namespace))
                    {
                        throw new DefaultException("filter {$className} does not exists!",404);
                    }

                    foreach($f2['methods'] as $mtd)
                    {
                        if(!method_exists($namespace,$mtd))
                        {
                            throw new DefaultException("filter {$className}/{$mtd} not found!",404);
                        }
                    }

                    array_push($filterCollection[$k],
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
        

        $this->app->setParamConfig('http.filters',$filterCollection,'protected');

        return $this;
    }

    public function callSingleFilter(ControllerKora $instance, Array $filter, string $method, Array $services, String $type)
    {
       
        if(!in_array($type,['before','after']))
        {
            throw new DefaultException("allowed type filters are: (before) and (after)!",400);
        }

        if(!in_array($filter['namespace'], class_uses($instance)))
        {
            throw new DefaultException(sprintf("Controller {%s} does not contains definition for {%s} Filter!",$instance::class,$filter['class']),404);
        }

        if(in_array($method,$filter['methods']))
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

                 $responseFilter[$method] = $resp;
            } 
            catch (\Throwable $th) 
            {
                throw new DefaultException(sprintf("{%s} ocurred when call filter {%s}::{%s}!",$th->getMessage(),$instance::class, $method),500);
            }
    
        }
    
        return new FilterResponseKora($type,$responseFilter);
    }

    public function callFilter(ControllerKora $instance, Array $filters, Array $services, String $type)
    {
        if(!in_array($type,['before','after']))
        {
            throw new DefaultException("allowed type filters are: (before) and (after)!",400);
        }

        $responseFilter = [];
        
        if(!array_key_exists($type,$filters))
        {
            throw new DefaultException("Type filter {$type} is no allowed!",400);
        }

        $callFilters = $filters[$type];

        foreach($callFilters as $k => $filter)
        {
            if(!in_array($filter['namespace'], class_uses($instance)))
            {
                throw new DefaultException(sprintf("Controller {%s} does not contains definition for {%s} Filter!",$instance::class,$filter['class']),404);
            }
     
            foreach($filter['methods'] as $mtd)
            {
                $params = [];

                if(array_key_exists($mtd,$services[$type]))
                {
                    $params = $services[$type][$mtd];
                }
               
                try 
                {
                     $responseFilter[$mtd] = $instance->$mtd(...$params);
                } 
                catch (\Throwable $th) 
                {
                    throw new DefaultException(sprintf("{%s} ocurred when call filter {%s}::{%s}!",$th->getMessage(),$instance::class, $mtd),500);
                }
     
            }
        }

        return new FilterResponseKora($type,$responseFilter);
    }
}
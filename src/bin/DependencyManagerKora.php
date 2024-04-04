<?php 
namespace kora\bin;

use kora\lib\exceptions\DefaultException;
use kora\lib\strings\Strings;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;


class DependencyManagerKora
{
    private Appkora $app;
    private Array $services;
    private Array $defaultValues = [];

    public function __construct(AppKora $app)
    {
        $this->app = $app;
        $cUrl = $this->app->getParamConfig("config.http.request.cUrl",'public');
        $this->services = $this->app->getParamConfig("routes.{$cUrl}.services","protected",false) ?? [];
    
        $this->defaultValues = 
        [
            "string" => Strings::empty,
            "int" => 0,
            "float" => 0,
            'bool' => true,
            'array' => []
        ];
    }

    private function extract($service,$key)
    {
        return array_key_exists($key,$service) ? $service[$key] : [];
    }



    private function resolveBuiltInDependencies(Array &$dependencies, $parameterType, $parameter)
    {

        if 
        (
            $parameterType === null 
            || 
            $parameterType->isBuiltin()
        ) 
        {

            $dependencies[] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : (array_key_exists($parameterType->getName(),$this->defaultValues) ? $this->defaultValues[$parameterType->getName()] : throw new RuntimeException("Unsupported parameter type for parameter {$parameter->getName()}!"));        
        }
    }

    public function resolve(String $namespaceClass)
    {
        $reflectionClass = new ReflectionClass($namespaceClass);

        if (!$reflectionClass->isInstantiable()) {
            throw new RuntimeException("Class {$namespaceClass} is not instantiable.");
        }

        $constructor = $reflectionClass->getConstructor();

        if (!$constructor) {
            return new $namespaceClass();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) 
        {
            $parameterType = $parameter->getType();

            if 
            (
                $parameterType === null 
                || 
                $parameterType->isBuiltin()
            ) 
            {
                $this->resolveBuiltInDependencies($dependencies,$parameterType,$parameter);
            }
            else if($parameterType instanceof ReflectionNamedType)
            {
                $dependencyClassName = $parameterType->getName();

                $dependencies[] = $this->resolve($dependencyClassName);
            }
            else 
            {
                throw new RuntimeException("Unsupported parameter type for parameter {$parameter->getName()} in class $namespaceClass.");
            }
        }

        return $reflectionClass->newInstanceArgs($dependencies);
    }


    private function resolveCretedInstances(Array &$resolved, string $nameMethod) : void
    {
        $objects = $this->extract($this->services[$nameMethod],'object');

        $injectables = $this->app->injectables();

        for($i = 0; $i < count($objects); ++$i)
        {
            $key = mb_substr(strrchr($objects[$i], '\\'),1);

            $resolved[$objects[$i]] = null;
      
            if(array_key_exists($objects[$i],$injectables))
            {
                $resolved[$key] = $injectables[$objects[$i]];
            }
        }
    }

    private function resolvedNullInstances(Array &$resolved) : void
    {
        foreach($resolved as $type => $instance)
        {
            if($instance == null)
            {
                $key = mb_substr(strrchr($type, '\\'),1);

                $resolved[$key] = $this->resolve($type);
                unset($resolved[$type]);
            }
        }
    }

    private function resolveParameters(Array &$resolved,string $nameMethod)
    {
        $params = $this->extract($this->services[$nameMethod],'params');

        foreach($params as $k => $value)
        {
            $resolved[$k] = $value;

            if(mb_substr($value,0,1) === ':')
            {
                  $p = mb_substr($value,1);
                  $parameters = $this->app->getParamConfig('config.http.parameters','public');
                  
                  if(array_key_exists($p,$parameters))
                  {
                        $resolved[$p] = $parameters[$p];
                  }
            }
            
        }
    }

    public function resolveConstructorDependencies() : array
    {
        $resolved = [];

        if(array_key_exists('constructor',$this->services))
        {
            $this->resolveCretedInstances($resolved,'constructor');
            $this->resolvedNullInstances($resolved);
            $this->resolveParameters($resolved,'constructor');
        }

        return $resolved;
    }

    public function resolveRouteDependencies() : array
    {
        $resolved = [];

        $aUrl = $this->app->getParamConfig('config.http.request.aUrl','public');

        if(array_key_exists($aUrl,$this->services))
        {
            $this->resolveCretedInstances($resolved, $aUrl);
            $this->resolvedNullInstances($resolved);
            $this->resolveParameters($resolved, $aUrl);
        }

        return $resolved;
    }

    public function resolveFiltersDependencies($type) : array
    {
        $resolved = [
            'before' => [],
            'after' => []
        ];

        if(!in_array($type,['before','after']))
        {
            throw new DefaultException("allowed type filters are: (before) and (after)!",400);
        }

        $filters = $this->app->getParamConfig("config.http.filters.{$type}",'public',false);

        foreach($filters as $filter)
        {
            for($i = 0; $i < count($filter['methods']); ++$i)
            {
                $aUrl = $filter['methods'][$i];

                if(array_key_exists($aUrl,$this->services))
                {
                    $resolved[$type][$aUrl] = [];
                    $this->resolveCretedInstances($resolved[$type][$aUrl], $aUrl);
                    $this->resolvedNullInstances($resolved[$type][$aUrl]);
                    $this->resolveParameters($resolved[$type][$aUrl], $aUrl);
                }
            }
        }


        return $resolved;
    }
}
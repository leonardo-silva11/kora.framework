<?php 
namespace kora\bin;

use kora\lib\collections\Collections;
use kora\lib\exceptions\DefaultException;
use kora\lib\strings\Strings;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;


class DependencyManagerKora
{
    private Appkora $app;
    private Array $services;
    private Array $defaultValues =  
    [
        "string" => Strings::empty,
        "int" => 0,
        "float" => 0,
        'bool' => true,
        'array' => [],
        'mixed' => null
    ];

    public function __construct(array $config)
    {
        $this->app = $config['app']['instance'];
        $this->services = $this->app->getParamConfig("http.route.services",'protected',false) ?? [];
        $this->app->setParamConfig('http.services',$this->services,'protected');
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

            $injectables = $this->app->injectables();

            if(array_key_exists($parameter->getName(),$injectables))
            {
               
                $dependencies[] = $injectables[$parameter->getName()];
            }
            else
            {       

                $key = $parameterType != null && method_exists($parameterType,'getName') ? $parameterType->getName() : 'mixed';
                $dependencies[] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() 
                : (array_key_exists($key,$this->defaultValues) 
                ? $this->defaultValues[$key] 
                : throw new RuntimeException("Unsupported parameter type for parameter {$parameter->getName()}!"));    
                
            }   
        }
    }

    private function resolveDependencyAlias(string $namespaceClass)
    {
        $response = [
            'namespace' => $namespaceClass,
            'alias' => basename(str_replace('\\', '/', $namespaceClass))
        ];

        $parts = explode(chr(32),$namespaceClass);
        $filteredParts = array_values(array_filter($parts));
        $count = count($filteredParts);

        if($count != 1 && $count != 3)
        {
            throw new RuntimeException("Invalid definition for dependency {$namespaceClass}!");
        }

        if($count === 3)
        {
            $response = [
                'namespace' => $filteredParts[0],
                'alias' => $filteredParts[2]
            ];
        }

        return $response;
    }

    public function resolve(string $namespaceClass)
    {
        
        $aliasDependency = $this->resolveDependencyAlias($namespaceClass);
       
        $reflectionClass = new ReflectionClass($aliasDependency['namespace']);
       
        if (!$reflectionClass->isInstantiable()) {
            throw new RuntimeException("Class {$aliasDependency['namespace']} is not instantiable.");
        }

        $constructor = $reflectionClass->getConstructor();

        if (!$constructor) 
        {
            $nameClass = $aliasDependency['namespace']; 
            return new $nameClass();
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
                $injectables = $this->app->injectables();
                $dependencyClassName = $parameterType->getName();
                $dependencies[] = array_key_exists($dependencyClassName,$injectables) ? $injectables[$dependencyClassName] : $this->resolve($dependencyClassName);
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
            $resolved[$objects[$i]] = null;
            
            if(array_key_exists($objects[$i],$injectables))
            {   
                $resolved[$objects[$i]] = $injectables[$objects[$i]];
            }
            else
            {
                $info = $this->resolveDependencyAlias($objects[$i]);

                foreach($injectables as $instanceInjectable)
                {
                    $typeItem = gettype($instanceInjectable);

                    if($typeItem == 'object')
                    {
                        $typeClass = get_class($instanceInjectable);

                        if($typeClass === $info['namespace'])
                        {
                            $resolved[$objects[$i]] = $instanceInjectable;
                        }
                    }
                }
            }
   
        }
        
    }

    private function resolvedNullInstances(Array &$resolved) : void
    {
        foreach($resolved as $type => $instance)
        {     
            if($instance == null)
            {
                $resolved[$type] = $this->resolve($type);
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

    private function resolveRequestHttp(Array &$resolved,string $nameMethod)
    {
        $params = $this->extract($this->services[$nameMethod],'requestHttp');
        
        $parameters = $this->app->getParamConfig('http.route.params.parameters','protected');

        foreach($params as $k)
        {
            if(array_key_exists($k,$parameters))
            {
                  $resolved[$k] = $parameters[$k];
            }            
        }
    }

    private function resolveRouteFixedParameters(Array &$resolved,string $nameMethod)
    {
        $params = $this->extract($this->services[$nameMethod],'fixedParams');

        $keys = \array_keys($params);
        foreach($keys as $k)
        {
            $resolved[$k] = $params[$k];
        }
       
        return $params;
    }

    private function prepareDependencies($resolvedDependencies)
    {
        $dependencies = [];

        foreach($resolvedDependencies as $k => $dependency)
        {           
           $info = $this->resolveDependencyAlias($k, $dependency);

           $dependencies[$info['alias']] = $dependency;
        }

        return $dependencies;
    }

    public function resolveConstructorDependencies() : array
    {
        $resolved = [];

        if(array_key_exists('constructor',$this->services))
        {
            $this->resolveCretedInstances($resolved,'constructor');
            $this->resolvedNullInstances($resolved,'constructor');
            $this->resolveParameters($resolved,'constructor');
        }
     
        return $this->prepareDependencies($resolved);
    }

    public function resolveRouteDependencies() : array
    {
        $resolved = [];

        $aUrl = $this->app->getParamConfig('http.action.name','protected');

        if(array_key_exists($aUrl,$this->services))
        {
            $this->resolveCretedInstances($resolved, $aUrl);
            $this->resolvedNullInstances($resolved);
            $this->resolveParameters($resolved, $aUrl);
        }

        return $this->prepareDependencies($resolved);
    }

    private function resolveInstance(string $type, string $filterClass, string $method, array &$resolved)
    {
        $middlewares = $this->app->getParamConfig("http.middlewares.{$type}",'protected',false);

        $filter = array_map(function($item) use ($filterClass) 
        {
            return $item['class'] == $filterClass ? $item['methods'] : [];

        }, $middlewares);
   
        if
            (
                !empty($filter) 
                && 
                !empty($filterClass) 
                && 
                !empty($method) 
                && 
                in_array($method,$filter[0])
            )
        {


            if(Collections::arrayKeyExistsInsensitive($method,$this->services))
            {
     
                $resolved[$type][$method] = [];

                $this->resolveCretedInstances($resolved[$type][$method], $method);
                $this->resolvedNullInstances($resolved[$type][$method]);
                $this->resolveParameters($resolved[$type][$method], $method);
                $this->resolveRouteFixedParameters($resolved[$type][$method], $method);
                $this->resolveRequestHttp($resolved[$type][$method], $method);
                $resolved[$type][$method] = $this->prepareDependencies($resolved[$type][$method]);   
                       
            }

        }
   
        return $resolved;
    } 

    public function resolveSingleMiddlewareDependencies(string $type, string $filterClass, string $method) : array
    {
        $resolved = [
            'before' => [],
            'after' => []
        ];

        $this->resolveInstance
        (
            $type,
            $filterClass,
            $method,
            $resolved
        );

        return $resolved;
    }

    public function resolveMiddlewareDependencies(string $type) : array
    {
        $resolved = [
            'before' => [],
            'after' => []
        ];

        if(!in_array($type,['before','after']))
        {
            throw new DefaultException("allowed type middlewares are: (before) and (after)!",400);
        }

        $middlewares = $this->app->getParamConfig("config.http.middlewares.{$type}",'public',false);

        foreach($middlewares as $filter)
        { 
            for($i = 0; $i < count($filter['methods']); ++$i)
            {
                
                    $this->resolveInstance
                    (
                        $type,
                        $filter['class'],
                        $filter['methods'][$i],
                        $resolved
                    );
                /*$aUrl = $filter['methods'][$i];
           
                if(array_key_exists($aUrl,$this->services))
                {

                    $resolved[$type][$aUrl] = [];
                    $this->resolveCretedInstances($resolved[$type][$aUrl], $aUrl);
                    $this->resolvedNullInstances($resolved[$type][$aUrl]);
                    $this->resolveParameters($resolved[$type][$aUrl], $aUrl);
                    $resolved[$type][$aUrl] = $this->prepareDependencies($resolved[$type][$aUrl]);
                }*/
            }
        }
        
        return $resolved;
    }

    public function filterRouteParameters
    (
        ControllerKora $controller,
        string $action = null,
        array $httpParameters = null,
        array $routeDependencies = null
    )
    {
        $action = $action ?? $this->app->getParamConfig('http.action.name','protected');
        $httpParameters = $httpParameters ?? $this->app->getParamConfig('http.route.params.parameters','protected');
        $routeDependencies = $routeDependencies ?? $this->resolveRouteDependencies();
        $reflection = new ReflectionMethod($controller, $action);
        $mParameters = $reflection->getParameters();

        $parameters = [];

        foreach($mParameters as $param)
        {
            if(array_key_exists($param->name,$httpParameters))
            {
                $parameters[$param->name] = $httpParameters[$param->name];
            }
            else if(array_key_exists($param->name,$routeDependencies))
            {
                $parameters[$param->name] = $routeDependencies[$param->name];
            }
        }
        
        return $parameters;
    }
}
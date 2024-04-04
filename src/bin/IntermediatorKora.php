<?php
namespace kora\bin;

use kora\lib\exceptions\DefaultException;
use ReflectionObject;

abstract class IntermediatorKora
{    
    private static AppKora $app;
    private static DependencyManagerKora $serviceContainer;
    private static FilterKora $filterKora;
    private static ControllerKora $controller;
    private IntermediatorKora $intermediator;

    protected function __construct(IntermediatorKora $intermediator)
    {
        if (!($intermediator instanceof IntermediatorKora)) 
        {
            throw new DefaultException(sprintf("An instance of {%s} is expected!",IntermediatorKora::class),500);
        }

        $this->intermediator = $intermediator; 
    }

    private function callAction(array $data)
    {
        $filterResponseAfter = self::callFilter('after');
        $aUrl = self::$app->getParamConfig('config.http.request.aUrl','public');

        if(!method_exists($this->intermediator,$aUrl))
        {
            throw new DefaultException(sprintf("the method {%s} not found in intermediator {%s}",$aUrl,$this->intermediator::class),404);
        }

        $appNameClass = self::$app->getParamConfig('config.className','public');
        $config = self::$app->getParamConfig('config.http.request','public');
        $settings = self::$app->getParamConfig("appSettings.apps.{$appNameClass}",'public');
        $bagConfig = array_merge($config,$settings);
        $response = new IntermediatorResponseKora($data,$bagConfig,$filterResponseAfter);

        $reflectionObject = new ReflectionObject($this->intermediator);
        $reflectionMethod = $reflectionObject->getMethod($aUrl);
        $paramMethod = $reflectionMethod->getParameters();

        if($paramMethod[0]->getType() != IntermediatorResponseKora::class)
        {
            throw new DefaultException
                    (sprintf("the method {%s::%s} must provide for receiving parameter {%s}, {%s} given!",
                        $this->intermediator::class,
                        $aUrl,
                        IntermediatorResponseKora::class,
                        $paramMethod[0]->getType()
                    ),404);
        }

        $this->intermediator->{$aUrl}($response);
    }
  
    public function view(array $data = [])
    {
        $this->callAction($data);
    }

    private static function start
        (
            AppKora $app, 
            DependencyManagerKora $serviceContainer, 
            FilterKora $filterKora,
            ControllerKora $controller
        ) : FilterResponseKora
    {
        self::$app = $app;
        self::$filterKora = $filterKora;
        self::$serviceContainer = $serviceContainer;
        self::$controller = $controller;

        return self::callFilter('before');
    }

    private static function callFilter(string $key) : FilterResponseKora
    {
        $filters = self::$app->getParamConfig('config.http.filters','public');
        $services = self::$serviceContainer->resolveFiltersDependencies($key);
        return self::$filterKora->callFilter(self::$controller,$filters,$services,$key);
    }

    
   /* private function renderView(HarpApplicationInterface $Application, HarpServer $ServerConfig)
    {
        $this->Application = $Application;
        $this->ServerConfig = $ServerConfig;
        $routeCurrent = $this->Application->getProperty(RouteEnum::class);

        $this->viewPaths[ViewEnum::Group->value] = mb_strtolower($routeCurrent[RouteEnum::Group->value]);

        if(!array_key_exists(RouteEnum::Group->value,$routeCurrent) || empty($routeCurrent[RouteEnum::Group->value]))
        {
            throw new \Exception('Group View is not defined!',500);
        }

        $viewGroup = sprintf
        (
            '%s%s%s%s%s%s%s%s',
            $this->Application->getAppNamespace(),
            '\\modules',
            '\\',
            $routeCurrent[RouteEnum::Module->value], 
            '\\view',
            '\\',
            $routeCurrent[RouteEnum::Group->value],
            'View',

        );
        
        $this->setProperty(ViewEnum::ServerVar->value,$this->ServerConfig->getAll());
        $this->setProperty(ViewEnum::Resources->value,$this->Application->getProperty(ViewEnum::Resources->value));
        $this->setProperty(ViewEnum::RouteCurrent->value,$this->viewPaths);

        $ViewObj = new $viewGroup($this);
        $viewName = $this->viewPaths[ViewEnum::Action->value];

        if(is_callable([$ViewObj,$viewName]))
        {
            $ViewObj->$viewName();
        }
    } */  
}

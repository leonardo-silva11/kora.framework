<?php
namespace kora\bin;

use kora\lib\exceptions\DefaultException;
use kora\lib\strings\Strings;
use ReflectionObject;

abstract class IntermediatorKora
{    
    private static AppKora $app;
    private static DependencyManagerKora $serviceContainer;
    private static FilterKora $filterKora;
    private static ControllerKora $controller;
    private IntermediatorKora $intermediator;
    private array $bagConfig = [];
    protected ?string $action;

    public function getAction()
    {
        return $this->action;
    }

    protected function __construct(IntermediatorKora $intermediator, ?string $action)
    {
        $this->action = $action;

        if (!($intermediator instanceof IntermediatorKora)) 
        {
            throw new DefaultException(sprintf("An instance of {%s} is expected!",IntermediatorKora::class),500);
        }
        else if(!stripos(IntermediatorKora::class, 'Intermediator'))
        {
            throw new DefaultException(sprintf("Invalid name {%s}. The intermediate class must contain the suffix or prefix {Intermediator} in its name!",IntermediatorKora::class),500);
        }

        $this->intermediator = $intermediator; 
        
        $this->config();

        return $this->intermediator;
    }

    private function getCurrentPage(mixed $template)
    {
        $shortName = basename(str_ireplace(['\\'],['/'],$this->intermediator::class));
        $viewName = str_ireplace(['\\','Intermediator'],['/',Strings::empty],$shortName);
        $directoryView = mb_strtolower($viewName);
        $aUrl = $this->intermediator->getAction()  ?? 
                    self::$app->getParamConfig('config.http.request.aUrl','public');

        $currentTemplate = (!empty($this->bagConfig['views']['defaultTemplate']) ? $this->bagConfig['views']['defaultTemplate'] : throw new DefaultException('No valid templates found!',404));

        if(!empty($template))
        {
            if(array_key_exists($template,$this->bagConfig['views']['templates']))
            {
                $currentTemplate = $this->bagConfig['views']['templates'][$template];
            }
            else if(in_array($template,$this->bagConfig['views']['templates']))
            {
                $key = array_search($template,$this->bagConfig['views']['templates']);

                if($key)
                {
                    $currentTemplate = $this->bagConfig['views']['templates'][$key];
                }
            }
        }

        return [
            'fullName' => $this->intermediator::class,
            'shortName' => $shortName,
            'viewName' =>  $viewName,
            'directoryView' => $directoryView,
            'template' => $currentTemplate,
            'action' => $aUrl
        ];
    }

    private function config()
    {
        $appNameClass = self::$app->getParamConfig('config.className','public');
        $config = self::$app->getParamConfig('config.http.request','public');
        $settings = self::$app->getParamConfig("appSettings.apps.{$appNameClass}",'public');
      
        if(array_key_exists('connectionStrings',$settings))
        {
            unset($settings['connectionStrings']);
        }

        $this->bagConfig = array_merge($config,$settings);
    }

    private function callAction(array $data, mixed $template)
    {
        $this->bagConfig['currentPage'] = $this->getCurrentPage($template);

        $filterResponseAfter = self::callFilter('after');
       
        $aUrl = $this->bagConfig['currentPage']['action'];

        if(!method_exists($this->intermediator,$aUrl))
        {
            throw new DefaultException(sprintf("the method {%s} not found in intermediator {%s}",$aUrl,$this->intermediator::class),404);
        }

        if
        (
            !array_key_exists('views',$this->bagConfig)
            ||
            !array_key_exists('defaultPageExtension',$this->bagConfig['views'])
            ||
            !array_key_exists('defaultTemplate',$this->bagConfig['views'])
            ||
            !array_key_exists('templates',$this->bagConfig['views'])
            ||
            !is_array($this->bagConfig['views']['templates'])
        )
        {
            throw new DefaultException("One or more views settings are missing, 
                                        check the views section of your appsettings.json to see if any of the following are missing: 
                                        [defaultPageExtension,defaultTemplate,templates]!",404);
        }




        $request = self::$app->getParamConfig('config.request','public');
        $response = new IntermediatorResponseKora($data, $this->bagConfig, $filterResponseAfter, $request);


        if(!$response->isRequestAjax())
        {
            $response->reponseView
            (
                new ReflectionObject($this->intermediator),
                $aUrl
            );
        }
        else
        {
            return $response->getJsonReponse();
        }
    } 
  
    public function view(array $data = [], string|int|null  $template = null)
    {
        $this->callAction($data,$template);
    }

    private static function start
        (
            AppKora $app, 
            DependencyManagerKora $serviceContainer, 
            FilterKora $filterKora,
            ControllerKora $controller
        ) : BagKora
    {
        self::$app = $app;
        self::$filterKora = $filterKora;
        self::$serviceContainer = $serviceContainer;
        self::$controller = $controller;

        return self::callFilter('before');
    }

    private static function callFilter(string $key) : BagKora
    {
        $filters = self::$app->getParamConfig('config.http.filters','public');
        $nameBag = sprintf("filters%s",ucfirst($key));
        $bag = new BagKora($nameBag);

        if(array_key_exists($key,$filters))
        {
            foreach($filters[$key] as $filter)
            {
                $methods = $filter['methods'];
               
                for($i = 0; $i < count($methods); ++$i)
                {
                    $class = $filter['class'];
                    $method = $methods[$i];
                    $services = self::$serviceContainer->resolveSingleFiltersDependencies($key,$class,$method);
                    $response = self::$filterKora->callSingleFilter(self::$controller,$filter,$methods[$i],$services,$key);
                    self::$app->addInjectable($response->getName(),$response);
                    $bag->add($response->getName(),$response);
                }
            }
        }

        return $bag;
    }
}

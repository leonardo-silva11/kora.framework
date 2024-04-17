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

    protected function __construct(IntermediatorKora $intermediator)
    {
        if (!($intermediator instanceof IntermediatorKora)) 
        {
            throw new DefaultException(sprintf("An instance of {%s} is expected!",IntermediatorKora::class),500);
        }

        $this->intermediator = $intermediator; 
        
        $this->config();

        return $this->intermediator;
    }

    private function getCurrentPage()
    {
        $shortName = basename(str_ireplace(['\\'],['/'],$this->intermediator::class));
        $viewName = str_ireplace(['\\','Intermediator'],['/',Strings::empty],$shortName);
        $directoryView = mb_strtolower($viewName);
        return [
            'fullName' => $this->intermediator::class,
            'shortName' => $shortName,
            'viewName' =>  $viewName,
            'directoryView' => $directoryView
        ];
    }

    private function config()
    {
        $appNameClass = self::$app->getParamConfig('config.className','public');
        $config = self::$app->getParamConfig('config.http.request','public');
        $settings = self::$app->getParamConfig("appSettings.apps.{$appNameClass}",'public');
        $this->bagConfig = array_merge($config,$settings);
        $this->bagConfig['currentPage'] = $this->getCurrentPage();

    }

    private function callAction(array $data, ?string $template)
    {
        $filterResponseAfter = self::callFilter('after');
       
        $aUrl = $this->intermediator->getAction()  ?? 
                    self::$app->getParamConfig('config.http.request.aUrl','public');

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

        $this->bagConfig['views']['currentTemplate'] = !empty($template) && in_array($template,$this->bagConfig['views']['templates']) 
                                                       ? $template 
                                                       : (!empty($this->bagConfig['views']['defaultTemplate']) ? $this->bagConfig['views']['defaultTemplate'] : throw new DefaultException('No valid templates found!',404));

        $response = new IntermediatorResponseKora($data, $this->bagConfig, $filterResponseAfter);
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
  
    public function view(array $data = [], $template = null)
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

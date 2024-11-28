<?php
namespace kora\bin;

use kora\lib\exceptions\DefaultException;
use kora\lib\strings\Strings;
use Symfony\Component\HttpFoundation\Response;

abstract class IntermediatorKora
{    
    private static AppKora $app;
    private static DependencyManagerKora $serviceContainer;
    private static MiddlewareKora $middlewareKora;
    private static ControllerKora $controller;
    private IntermediatorKora $intermediator;
    private array $bagConfig = [];
    protected ?string $action;
    protected int $code = 200;

    protected function __construct(IntermediatorKora $intermediator, ?string $action, int $code = 200)
    {
        $this->intermediator = $intermediator; 
        $this->action = $action;
        $this->setCode($code);

        if (!($intermediator instanceof IntermediatorKora)) 
        {
            throw new DefaultException(sprintf("An instance of {%s} is expected!",IntermediatorKora::class),500);
        }
        else if(!stripos(IntermediatorKora::class, 'Intermediator'))
        {
            throw new DefaultException(sprintf("Invalid name {%s}. The intermediate class must contain the suffix or prefix {Intermediator} in its name!",IntermediatorKora::class),500);
        }

        $this->config();

        return $this->intermediator;
    }

    public function setCode($code)
    {
        $this->code = is_int($code) && $code >= 100 && $code <= 599 ? $code : throw new DefaultException("The code http {$code} is inválid!");
        return $this->intermediator;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getAction()
    {
        return $this->action;
    }

    private function getCurrentPage(mixed $template)
    { 
        $shortName = basename(str_ireplace(['\\'],['/'],$this->intermediator::class));
        $viewName = str_ireplace(['\\','Intermediator'],['/',Strings::empty],$shortName);
        $directoryView = mb_strtolower($viewName);

        $action = $this->intermediator->getAction()  ?? 
                    self::$app->getParamConfig('http.action.name','protected');
 
        $currentTemplate = (!empty($this->bagConfig['settings']['views']['defaultTemplate']) ? $this->bagConfig['settings']['views']['defaultTemplate'] : throw new DefaultException('No valid templates found!',404));

        if(!empty($template))
        {
            if(array_key_exists($template,$this->bagConfig['settings']['views']['templates']))
            {
                $currentTemplate = $this->bagConfig['settings']['views']['templates'][$template];
            }
            else if(in_array($template,$this->bagConfig['settings']['views']['templates']))
            {
                $key = array_search($template,$this->bagConfig['settings']['views']['templates']);

                if($key)
                {
                    $currentTemplate = $this->bagConfig['settings']['views']['templates'][$key];
                }
            }
        }

        return [
            'fullName' => $this->intermediator::class,
            'shortName' => $shortName,
            'viewName' =>  $viewName,
            'directoryView' => $directoryView,
            'template' => $currentTemplate,
            'action' => $action
        ];
    }

    private function config()
    {
        $appNameClass = self::$app->getParamConfig('app.class','protected');
        $config = self::$app->getParamConfig('http.request','protected');
        $settings = self::$app->getParamConfig("appSettings.apps.{$appNameClass}",'protected');
        $paths = self::$app->getParamConfig('paths','protected');

        if(array_key_exists('connectionStrings',$settings))
        {
            unset($settings['connectionStrings']);
        }
        
        if(array_key_exists('clientCredentials',$settings))
        {
            unset($settings['clientCredentials']);
        }

        $this->bagConfig = ['config' => $config, 'settings' => $settings, 'paths' => $paths];
    }

    private function callAction(array $data, mixed $template)
    {
        $this->bagConfig['currentPage'] = $this->getCurrentPage($template);

        $filterResponseAfter = self::callFilter('after');

        $action = $this->bagConfig['currentPage']['action'];

        if(!method_exists($this->intermediator,$action))
        {
            throw new DefaultException(sprintf("the method {%s} not found in intermediator {%s}",$action,$this->intermediator::class),404);
        }

        if
        (
            !array_key_exists('views',$this->bagConfig['settings'])
            ||
            !array_key_exists('defaultPageExtension',$this->bagConfig['settings']['views'])
            ||
            !array_key_exists('defaultTemplate',$this->bagConfig['settings']['views'])
            ||
            !array_key_exists('templates',$this->bagConfig['settings']['views'])
            ||
            !is_array($this->bagConfig['settings']['views']['templates'])
        )
        {
            throw new DefaultException("One or more views settings are missing, 
                                        check the views section of your appsettings.json to see if any of the following are missing: 
                                        [defaultPageExtension,defaultTemplate,templates]!",404);
        }
 
        $request = self::$app->getParamConfig('http.request.instance','protected');
      
        return new IntermediatorResponseKora
        (
            $data, 
            $this->bagConfig, 
            $filterResponseAfter, 
            $request,
            $this->intermediator
        );
    } 
  
    public function view(array $data = [], string|int|null  $template = null)
    {
        return $this->callAction($data,$template);
    }

    private static function start
        (
            AppKora $app, 
            DependencyManagerKora $serviceContainer, 
            MiddlewareKora $middlewareKora,
            ControllerKora $controller
        ) : BagKora|IntermediatorResponseKora
    {
        self::$app = $app;
        self::$middlewareKora = $middlewareKora;
        self::$serviceContainer = $serviceContainer;
        self::$controller = $controller;

        return self::callFilter('before');
    }

    private static function callFilter(string $key) : BagKora|IntermediatorResponseKora
    {
        $middlewares = self::$app->getParamConfig('http.middlewares');
        $keyBag = ucfirst($key);
        $nameBag = "middlewares{$keyBag}";
        $bag = new BagKora($nameBag);

        if(array_key_exists($key,$middlewares))
        {
            foreach($middlewares[$key] as $filter)
            {

                $class = $filter['class'];

                $methods = $filter['methods'];
           
                for($i = 0; $i < count($methods); ++$i)
                {
                    $method = $methods[$i];
         
                    $services = self::$serviceContainer->resolveSingleMiddlewareDependencies($key,$class,$method);
        
                    $response = self::$middlewareKora->callSingleMiddleware(self::$controller,$filter,$method,$services,$key);

                    $data = $response->getResponse($method);
                    self::$app->addInjectable($response->getName(),$response);
                    $bag->add($response->getName(),$response);
           
                    if(gettype($data) == 'object' && ($data instanceof IntermediatorResponseKora || $data instanceof Response))
                    {
                       return $data;
                    }
                }
            }
        }

        return $bag;
    }
}

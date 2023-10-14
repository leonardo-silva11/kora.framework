<?php 
namespace kora\bin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use JmesPath\Env;
use kora\lib\exceptions\DefaultException;

class RouterKora
{
    private static $request;
    private static $instance = null;
    private string $projectPath;
    private string $appPath;
    private AppKora $app;
    private Array $appSettings;


    private function __construct()
    {
        RouterKora::$request = Request::createFromGlobals();
        $this->projectPath = dirname(__DIR__,5);
        $this->appPath = "$this->projectPath/app";

        $this->settings();
        $this->config();
     
    }

    private function config()
    {
            // Obtenha informações do servidor com segurança
            //$method = RouterKora::$request->getMethod();
            //$uri = RouterKora::$request->getRequestUri();
            
            // Outros exemplos de informações disponíveis
          //  $serverInfo = RouterKora::$request->server->all();
            
            // Você pode acessar as variáveis diretamente
            $rqstUri = RouterKora::$request->getRequestUri();

            $parseUri = $this->uriToCollection($rqstUri);

            $defaultApp = $rqstUri === '/' || !$this->isApp($parseUri[0]);

            $this->parseApp($parseUri,$defaultApp);
    }

    private function uriToCollection(string $rqstUri) : array
    {
        $r = explode('/',$rqstUri);

        $r = array_filter($r, function ($value) 
        {
            return !empty($value) || $value === 0;
        });

        $r = array_values($r);
        
        return $r;
    }

    private function isApp(string $appName) : bool
    {
        return in_array($appName,$this->appSettings["apps"]);
    }

    private function configApp
        (
            $appName,
            $className,
        )
    {
        $className = ucfirst($appName);
        $namespace = "app\\$appName\\$className";
        $classPath = "$this->appPath/$appName/$className.php";

        if(!file_exists($classPath) || !class_exists($namespace))
        {
            throw new DefaultException("The app {$appName} class not found in: $this->appPath/$appName",500);
        }
        
        return [
            'appName' => $appName,
            'className' => $className,
            'namespace' => $namespace,
            'classPath' => $classPath
        ];
    }

    private function parseParameters(array $req)
    {
        dump($req);exit;
    }

    private function parseApp(array $parseUri, bool $isDefault)
    {

        $env = new Env();

        $appName = $isDefault ? $env->search('defaultApp',$this->appSettings) : $parseUri[0];

        if(empty($appName))
        {
            throw new DefaultException("Default app is not defined in appsettings.json");
        }

        $className = ucfirst($appName);
        $config = $this->configApp($appName,$className);

        $namespace = $config['namespace'];
        $this->parseParameters($parseUri);
        $this->app = new $namespace();
        
    }

    private function settings()
    {
        $str = file_get_contents("$this->projectPath/appsettings.json");
        
        $decodedJson = @json_decode($str,true);

        if(empty($decodedJson))
        {
            throw new DefaultException("appsettings.json does not contains configurations!",500,[
                'info' => 'create a defaultApp key in appsettings.json and defined the default app for this project.'
            ]);
        }

        $this->appSettings = $decodedJson;
    }

    public static function start()
    {
        if(empty(RouterKora::$instance))
        {
            RouterKora::$instance = new RouterKora();
        }
    }
}
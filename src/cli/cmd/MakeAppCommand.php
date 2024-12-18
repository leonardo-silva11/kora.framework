#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use Directory;
use kora\lib\storage\DirectoryManager;
use kora\lib\storage\FileManager;

class MakeAppCommand  extends CommandCli
{
    public function __construct(string $path)
    {
        parent::__construct($this,$path);

    }

    public function createControllerClass(DirectoryManager $dirManager,$app, $controller, $action, $front = true, $rewrite = false)
    {
        $MakeController = new MakeControllerCommand($this->paths['project']);
        $MakeController->createController($dirManager, $app, $controller, $action, $rewrite, $front);
    }

    public function createmodelClass(DirectoryManager $dirManager,$app, $model, $action)
    {
        $MakeModel = new MakeModelCommand($this->paths['project']);
        $MakeModel->createModel($dirManager, $app, $model, $action);
    }

    public function createIntermediatorClass(DirectoryManager $dirManager,$app, $model, $action)
    {
        $MakeIntermediator = new MakeIntermediatorCommand($this->paths['project']);
        $MakeIntermediator->createIntermediator($dirManager, $app, $model, $action);
    }

    public function createView(DirectoryManager $dirManager, $nameApp, $nameView, $action, $template, $ext)
    {
        $MakeView = new MakeViewCommand($this->paths['project']);
        $MakeView->createView($dirManager, $nameApp, $nameView, $action, $template, $ext);
    }


    public function creatAppClass(DirectoryManager $dirManager,$nameApp, $rewrite = false)
    {
        $basePath = dirname(__DIR__, 1);

        $file = new FileManager($dirManager);

        if(!$file->exists("$nameApp.php") || $rewrite)
        {
            $base = file_get_contents("$basePath/skeleton/AppSkeleton.kora");

            $fileClass = str_ireplace(                                
            [
                '{{__name}}',
            ],
            [
                $nameApp,
            ],$base);

            $file->save("$nameApp.php",$fileClass);

            $this->log->save("Class {$nameApp} created!");
        }
        else
        {
            $this->log->save("Class {$nameApp} alredy exists!");
        }

        $this->log->showAllBag(false);

        return $file->exists("$nameApp.php") && !$rewrite;
    }

    private function createFront
    (
        $MakeConfig, 
        $nameAppN,
        $front = false
    )
    {
        if($front)
        {
            $nameAppLower = strtolower($nameAppN);
            $defaultExtensionView = OptionsCli::getOption('--extension',$this->cmdArgs) ?? "html";
            $defaultTemplateView = OptionsCli::getOption('--template',$this->cmdArgs) ?? "$nameAppLower.v1.0";
            
            $MakeConfig->addSetting("apps.$nameAppN.views",[
                "defaultPageExtension" => $defaultExtensionView,
                "defaultTemplate" => $defaultTemplateView,
                "templates" => [$defaultTemplateView]
            ]);

            $dir = $this->directoryManager->createByPath($this->paths['app']);
            $controller = OptionsCli::getOption('--controller',$this->cmdArgs) ?? "Home";
            $nameControllerLower = strtolower($controller);
            $nameApp = OptionsCli::getArg(0,$this->cmdArgs);
            $action = OptionsCli::getOption('--action',$this->cmdArgs) ?? "index";

            if(!empty($nameApp))
            {
                $this->createIntermediatorClass($dir, $nameAppN, $nameControllerLower,$action);
                $this->createView($dir,$nameApp,$nameControllerLower, $action, $defaultTemplateView, $defaultExtensionView);
            }
        }
    }

    private function generateRSAPublicKey(FileManager $file, string $nameApp, $password)
    {

        $privateKeyPath = $file->inMemory("private-{$nameApp}.key");
        $publicKeyPath = $file->inMemory("public-{$nameApp}.key");
        $passphrase = $password;

        $command = sprintf(
            'openssl rsa -in %s -passin pass:%s -pubout -out %s',
            escapeshellarg($privateKeyPath),
            escapeshellarg($passphrase),
            escapeshellarg($publicKeyPath)
        );
        
        $process = proc_open(
            $command,
            [
                1 => ['pipe', 'w'], // Saída padrão
                2 => ['pipe', 'w'], // Saída de erro
            ],
            $pipes
        );
        
        if (is_resource($process)) 
        {
            $output = stream_get_contents($pipes[1]); // Captura a saída padrão (se necessário)
            $error = stream_get_contents($pipes[2]); // Captura a saída de erro
        
            fclose($pipes[1]);
            fclose($pipes[2]);
        
            $returnCode = proc_close($process);
        
            if ($returnCode === 0) 
            {
                $this->log->save("public key {public-{$nameApp}.key} extracted!");
            } 
            else 
            {
                $this->log->save("failed while generate {public-{$nameApp}.key}!",true);
            }
        } 
        else 
        {
            $this->log->save("command failed while generate key {public-{$nameApp}.key}!",true);
        }
        
    } 

    private function generateRSAPrivateKey(bool $appExists, MakeConfig $MakeConfig, string $nameApp, array $secretKeys)
    {
        $passphrase = $secretKeys['private'];
        $nameProject = basename($this->paths['project']);
        $dir = new DirectoryManager($nameProject);

        $file = new FileManager($dir);

        $nameFile = "private-{$nameApp}.key";

        if($appExists)
        {
            $passphrase = $MakeConfig->readSettingsByKey('apps.Oauth.secretKeys.private');
        }

        if(!$file->exists($nameFile))
        {
            $path = $file->inMemory($nameFile);

            $command = "openssl genrsa -aes128 -passout pass:$passphrase -out $path 2048";

            $descriptorspec = [
                0 => ["pipe", "r"], // stdin
                1 => ["pipe", "w"], // stdout
                2 => ["pipe", "w"]  // stderr
            ];
            
            $process = proc_open($command, $descriptorspec, $pipes);
            
            if (is_resource($process)) 
            {
                // Fechando as pipes stdin e capturando stdout e stderr
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
            
                $return_value = proc_close($process);
            
                if ($return_value === 0) 
                {
                    $this->log->save("private key {$nameFile} created!");
                } 
                else 
                {
                    $this->log->save("failed while generate {$nameFile}!",true);
                }
            } 
            else 
            {
                $this->log->save("command failed while generate key {$nameFile}!",true);
            }
        }
        
        $this->generateRSAPublicKey($file, $nameApp, $passphrase);
    }

    public function exec(array $args, $cmd = 'app')
    {
        $this->cmdArgs = array_values($args);

        $nameApp = OptionsCli::getArg(0,$this->cmdArgs);
     
        if(empty($nameApp))
        {
            $this->log->save('name of app not found!',true);
        }
      
        $nameAppArray = explode('-',$nameApp);
        $nameAppArray = count($nameAppArray) < 2 ? explode('_',$nameApp) : $nameAppArray;
        $nameAppArrayN = array_map('ucfirst', $nameAppArray);
        $nameAppN = implode('',$nameAppArrayN);
        $nameAppLower = strtolower($nameAppN);

        $forceBuild = OptionsCli::getOption('--rewrite',$this->cmdArgs) ?? false;
        $controller = OptionsCli::getOption('--controller',$this->cmdArgs) ?? "Home";
        $model = OptionsCli::getOption('--model',$this->cmdArgs) ?? $controller;
        $front = OptionsCli::getOption('--front',$this->cmdArgs) ?? false;
        $defaultExtensionView = OptionsCli::getOption('--extension',$this->cmdArgs) ?? "html";
        $defaultTemplateView = OptionsCli::getOption('--template',$this->cmdArgs) ?? "$nameAppLower.v1.0";
        $clientId = OptionsCli::getOption('--id_client',$this->cmdArgs) ?? "";
        $clientSecret = OptionsCli::getOption('--secret_client',$this->cmdArgs) ?? "";
        $action = OptionsCli::getOption('--action',$this->cmdArgs) ?? "index";
        $verb = OptionsCli::getOption('--verb',$this->cmdArgs) ?? "get";
        $nameControllerLower = strtolower($controller);
        $nameActionLower = strtolower($action);
        $MakeConfig = new MakeConfig($nameAppLower);
        
        $this->paths['app'] = $this->directoryManager->createInMemory($this->paths['project'],"app/$nameAppLower");
    
        if($cmd == 'controller' && $this->directoryManager->directoryExistsByFullPath($this->paths['app']))
        {
            $generateModel = OptionsCli::getOption('--model',$this->cmdArgs) ?? false;

            $dir = $this->directoryManager->createByPath($this->paths['app']);

            $this->createControllerClass($dir, $nameAppN, $controller, $action, $front, $forceBuild);

            if($generateModel)
            {
                $this->createModelClass($dir, $nameAppN, $controller, $action);
            }

            $this->createFront($MakeConfig,$nameAppN,$front);
        }
        else if($cmd == 'app')
        {
            $dir = $this->directoryManager->createByPath($this->paths['app']);

            $appExists = $this->creatAppClass($dir,$nameAppN);

            $secretKeys = [
                'public' => base64_encode(hash('sha512',uniqid('public'))),
                'private' => base64_encode(hash('sha512',uniqid('private'))),
            ];

            $this->generateRSAPrivateKey($appExists,$MakeConfig,$nameAppLower,$secretKeys);

            if(!$appExists)
            {
                $defaultExists = $MakeConfig->defaultRouteExists();

                if(!$defaultExists)
                {
                    $MakeConfig->addSetting('defaultApp',$nameAppLower);
                }



                $MakeConfig->addSetting("apps.{$nameAppN}",
                        [
                            "defaultType" => $front ? "app" : "api",
                            "defaultRoute" => "$nameControllerLower/$nameActionLower",
                            "name" => $nameAppLower,
                            "connectionStrings" => new \stdClass(),
                            "secretKeys" => $secretKeys,
                            "clientCredentials" => [
                                "clientId" => $clientId,
                                "clientSecret" => $clientSecret,
                            ]
                        ]
                    );
            }
           
            $this->createFront($MakeConfig,$nameAppN,$front);        
            $MakeConfig->settingsSave(true);
            $this->createControllerClass($dir, $nameAppN, $controller,$action,$front,$forceBuild);
            $this->createModelClass($dir, $nameAppN, $model,$action);
        }
  
        $MakeConfig->addRoute("$nameControllerLower/$nameActionLower",$controller,$action,$verb);
        $MakeConfig->routesSave();
    }
}

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

    public function createControllerClass(DirectoryManager $dirManager,$app, $controller, $action)
    {
        $MakeController = new MakeControllerCommand($this->paths['project']);
        $MakeController->createController($dirManager, $app, $controller, $action);
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
    }

    public function exec(array $args)
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
        $model = OptionsCli::getOption('--model',$this->cmdArgs) ?? "Home";
        $front = OptionsCli::getOption('--front',$this->cmdArgs) ?? false;
        $defaultExtensionView = OptionsCli::getOption('--extension',$this->cmdArgs) ?? "html";
        $defaultTemplateView = OptionsCli::getOption('--template',$this->cmdArgs) ?? "$nameAppLower.v1.0";
        $clientId = OptionsCli::getOption('--id_client',$this->cmdArgs) ?? "";
        $clientSecret = OptionsCli::getOption('--secret_client',$this->cmdArgs) ?? "";
        $action = OptionsCli::getOption('--action',$this->cmdArgs) ?? "index";

        $nameControllerLower = strtolower($controller);
        $nameActionLower = strtolower($action);

        $this->paths['app'] = $this->directoryManager->createInMemory($this->paths['project'],"app/$nameAppLower");

        $dir = $this->directoryManager->createByPath($this->paths['app']);

        $this->creatAppClass($dir,$nameAppN);
        $MakeConfig = new MakeConfig($nameAppLower);
        $MakeConfig->addSetting('defaultApp',$nameAppLower)
                    ->addSetting('apps',[
                        $nameAppN => [
                            "defaultType" => $front ? "app" : "api",
                            "defaultRoute" => "$nameControllerLower/$nameActionLower",
                            "name" => $nameAppLower,
                            "connectionStrings" => new \stdClass()
                        ]
                    ]);

        if($front)
        {
            $MakeConfig->addSetting("apps.$nameAppN.views",[
                "defaultPageExtension" => $defaultExtensionView,
                "defaultTemplate" => $defaultTemplateView,
                "templates" => [$defaultTemplateView]
            ]);

            $this->createIntermediatorClass($dir, $nameAppN, $nameControllerLower,$action);
            $this->createView($dir,$nameApp,$nameControllerLower, $action, $defaultTemplateView, $defaultExtensionView);
        }

        $MakeConfig->addSetting("apps.$nameAppN.clientCredentials",[
            "clientId" => $clientId,
            "clientSecret" => $clientSecret,
        ]);

        $MakeConfig->settingsSave();

        $this->createControllerClass($dir, $nameAppN, $controller,$action);
        $this->createModelClass($dir, $nameAppN, $model,$action);

    
        $MakeConfig->addRoute("$nameControllerLower/$nameActionLower",$controller,$action);
        $MakeConfig->routesSave();
    }
}

#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use DirectoryIterator;
use kora\lib\exceptions\DefaultException;
use Illuminate\Database\Capsule\Manager as Capsule;
use kora\lib\storage\DirectoryManager;
use kora\lib\support\Log;

//require dirname(__DIR__,3).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

class ORM
{
    private $dbConfig = [];
    private $settings = [];
    private $directorySeparator = DIRECTORY_SEPARATOR;
    private array $paths;
    private $app;
    private Log $log;
    private $cmdArgs = [];
    private $parametersCli = [
        '-t' => 'table',
        '-c' => 'create',
        '-m' => 'create'
    ]; 

    private $capsule;

    public function __construct(string $path)
    {
        $this->paths['app'] = $path; 

        $this->log = new Log(new DirectoryManager('php-sound-cli'));
    }

    public function config(array $args)
    {        
        $this->cmdArgs = array_values($args);

    
        $this->paths['entity'] = "{$this->paths['app']}{$this->directorySeparator}app{$this->directorySeparator}";
        $pathSettings = "{$this->paths['app']}{$this->directorySeparator}appsettings.json";

        if(!file_exists($pathSettings))
        {
            throw new DefaultException("File {appsettings.json} does not exists!",404);
        }

        $this->settings = json_decode(file_get_contents($pathSettings),true);

        $appKey = $this->getOption('--app',$args) ?? 1;
        $appConn = $this->getOption('--conn',$args) ?? 1;
        $appKey = (int)$appKey;
        $appConn = (int)$appConn;
        
        $this->dbConfig = $this->getDatabaseConfig($appKey,$appConn);

        $this->paths = 
        [
            'entity' => "{$this->paths['app']}{$this->directorySeparator}app{$this->directorySeparator}{$this->app['name']}{$this->directorySeparator}models{$this->directorySeparator}database{$this->directorySeparator}entity",
            'migrations' => "{$this->paths['app']}{$this->directorySeparator}app{$this->directorySeparator}{$this->app['name']}{$this->directorySeparator}models{$this->directorySeparator}database{$this->directorySeparator}migrations",
        ];

    }


    private function getOptions(array $options, $args)
    {
        $val = null;

        for($i = 0; $i < count($options); ++$i)
        {
            $val = $this->getOption($options[$i],$args);

            if(!empty($val))
            {
                break;
            }
        }

        return $val;
    }

    private function getOption($option,$args)
    {
        $result = array_filter($args, function($value)  use ($option)  {
            return strpos($value,$option) === 0;
        });

        $val = null;

        if(!empty($result))
        {
            $exp = explode('=',end($result));
            $val = array_key_exists(1,$exp) ? $exp[1] : $exp[0];
        }

        return $val;

    }

    private function getDatabaseConfig(int $appKey, int $appConn)
    {
        $apps = array_values($this->settings['apps']);
        $this->app = $apps[$appKey- 1];
        $connectionStrings = array_values($this->app['connectionStrings']);
        $configDatabase = $connectionStrings[$appConn - 1];
        return $configDatabase;
    }

    private function connect(): void
    {
        $this->capsule = new Capsule();
        $this->capsule->addConnection($this->dbConfig);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
        //$app = new Container();
        //Facade::setFacadeApplication($this->capsule->getContainer());
    }

    public function makeEntity()
    {
        if(!array_key_exists(0,$this->cmdArgs))
        {
            throw new DefaultException('Entity name not found',404);
        }

        $m = $this->getOption('-m',$this->cmdArgs);

        $namespace = "app\\{$this->app['name']}\\models\\database\\entity";

        $entityName = "{$this->cmdArgs[0]}Entity";
        $entity = <<<EOD
        <?php 
        namespace {$namespace};
        use Illuminate\Database\Eloquent\Model;
        class {$entityName} extends Model
        {
            public \$table = '{$this->cmdArgs[0]}';
        }
        EOD;

        file_put_contents("{$this->paths['entity']}{$this->directorySeparator}{$entityName}.php",$entity);

        if(!empty($m))
        {
            $this->makeMigration();
        }

        $this->log->save("entity created successfully: {$entityName}",true);
    }

    public function makeMigration()
    {
        clearstatcache();

        $arg = $this->getOptions(['-m','-c','-t'],$this->cmdArgs);
        $arg = $arg ?? '-c';
        $type = array_key_exists($arg,$this->parametersCli) ? $this->parametersCli[$arg] : throw new DefaultException("Invalid argument to make migration!",400);

        $createOrChange = $type === '-m' || mb_strtolower($type) === 'create'  ? 'create' : 'table';

        $prefixClass = ucfirst($createOrChange);

        $entityName = ucfirst($this->cmdArgs[0]);
        $tableName = "TB{$entityName}";
        $primaryKey = str_ireplace(['TB'],['XV'],$tableName);

        $migration =  <<<EOD
        <?php
        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Database\Capsule\Manager as CapsuleManager;
        use Illuminate\Database\Capsule\Manager as DB;

        class {$prefixClass}{$tableName} extends Migration
        {

            private DB \$DB;

            public function __construct(DB \$DB)
            {
                \$this->DB = \$DB;
            }
            /**
             * Run the migrations.
             *
             * @return void
             */
            public function up()
            {
                if(!DB::getSchemaBuilder()->hasTable('{$tableName}'))
                {
                    \$this->DB->schema()->{$createOrChange}('{$tableName}', function (Blueprint \$table) {
                        \$table->bigIncrements('{$primaryKey}');
                    });

                    return true;
                }

                return false;
            }

            /**
             * Reverse the migrations.
             *
             * @return void
             */
            public function down()
            {
                if(DB::getSchemaBuilder()->hasTable('{$tableName}'))
                {
                    //change to revert changes migrations
                    //\$this->DB->schema()->dropIfExists('{$tableName}');
                    //return true;
                }

                return false;
            }

            public function getTableName()
            {
                return '{$tableName}';
            }

            public function getMigrationName()
            {
                return '{$prefixClass}{$tableName}';
            }
        }    
        EOD;

        $pathSave = "{$this->paths['migrations']}{$this->directorySeparator}{$prefixClass}{$tableName}.php";
        
        $msg = "migration {$prefixClass}{$tableName} already exists!";

        if(!file_exists($pathSave))
        {
            file_put_contents($pathSave, $migration);
            $msg = "migration created successfully: {$prefixClass}{$tableName}";
        }

        $this->log->save($msg,true);
    }


    private function getMigrations()
    {
        $files = [];

        if(!empty($this->cmdArgs[0]) && substr($this->cmdArgs[0],0,6) != 'CreateTB')
        {
            $this->cmdArgs[0] = "CreateTB{$this->cmdArgs[0]}";
        }

        if
        (
            array_key_exists(0,$this->cmdArgs)
            &&
            file_exists("{$this->paths['migrations']}{$this->directorySeparator}{$this->cmdArgs[0]}.php")
        )
        {
            array_push($files,[
                'class' => $this->cmdArgs[0],
                'name' => "{$this->cmdArgs[0]}.php",
                'path' => "{$this->paths['migrations']}{$this->directorySeparator}{$this->cmdArgs[0]}.php",
                'createdAt' => filectime("{$this->paths['migrations']}{$this->directorySeparator}{$this->cmdArgs[0]}.php")
            ]);

            return $files;
        }

        $dirIterator = new DirectoryIterator("{$this->paths['migrations']}");

        foreach ($dirIterator as $fileInfo) 
        {
            if($fileInfo->isDot()) { continue; }

         
            array_push($files,[
                'class' => pathinfo($fileInfo->getFilename(), PATHINFO_FILENAME),
                'name' => $fileInfo->getFilename(),
                'path' => $fileInfo->getPathname(),
                'createdAt' => $fileInfo->getATime()
            ]);
        }

        usort($files, function ($f1, $f2) {
            return $f1['createdAt'] <=> $f2['createdAt'];
        });

        return $files;
    }

    public function execMigrations()
    {      
        $this->connect();

        $migrations = $this->getMigrations();

        //-u = up e -d = down
        $method = $this->getOptions(['-u','-d'],$this->cmdArgs);
        $method = $method == '-u' || empty($method) ? 'up' : 'down';

        foreach($migrations as $migration)
        {
            $class = $migration['class'];
            require_once($migration['path']);
     
            $objMigration = new $class($this->capsule);
         
            $msg = "table {$objMigration->getTableName()} already exists!";

            if($objMigration->$method())
            {
                $msg = "table {$objMigration->getTableName()} created successfully in database!";
            }

            $this->log->save($msg,true);
        }
    }


}
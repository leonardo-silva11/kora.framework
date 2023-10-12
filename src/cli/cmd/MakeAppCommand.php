#!/usr/bin/env php
<?php
namespace kora\cli\cmd;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MakeAppCommand
{
    public function __construct()
    {
        $app = new Application();

        $app->register('make:app')
            ->addArgument('name', InputArgument::REQUIRED)
            ->setCode(function ($input, $output) 
            {
                $name = $input->getArgument('name');
                $output->writeln("Criando um novo aplicativo {$name}...");
                
                
            });
            $app->run();
    }
}

<?php
namespace kora\cli\cmd;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class MakeAppCommand extends Command
{
    protected static $defaultName = 'make:app';

    protected function configure()
    {
        $this
            ->setDescription('Cria um novo app com arquivos de view')
            ->setHelp('Este comando cria um novo app web com estrutura de view.')
            ->addArgument('appName', InputArgument::REQUIRED, 'Nome do aplicativo (obrigatório)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
      

        $appName = $input->getArgument('appName');

        var_dump($appName);exit;
          // Implemente a lógica para criar a estrutura do aplicativo aqui
        // Por exemplo, criar controladores e arquivos necessários
        $output->writeln('Criando um novo aplicativo...');
        exit;
        return Command::SUCCESS;
    }
}

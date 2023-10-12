<?php
namespace kora\cli\cmd;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeAppCommand extends Command
{
    protected static $defaultName = 'make:app';

    protected function configure()
    {
        $this
            ->setDescription('Cria um novo app com arquivos de view')
            ->setHelp('Este comando cria um novo app web com estrtutura de view.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Implemente a lógica para criar a estrutura do aplicativo aqui
        // Por exemplo, criar controladores e arquivos necessários
        $output->writeln('Criando um novo aplicativo...');

        return Command::SUCCESS;
    }
}

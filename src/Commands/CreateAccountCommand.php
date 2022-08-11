<?php

namespace Rogierw\RwAcmeCli\Commands;

use Rogierw\RwAcmeCli\Actions\CreateAccountAction;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateAccountCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('account:create')
            ->setDescription('Create a new LE account.')
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');

        $output->writeln("<comment>Create an new account with the e-mail address {$email}.</comment>");

        (new CreateAccountAction)->execute($email);

        $output->writeln('<info>Account has been created.</info>');

        return Command::SUCCESS;
    }
}
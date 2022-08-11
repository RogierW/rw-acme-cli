<?php

namespace Rogierw\RwAcmeCli\Commands;

use Rogierw\RwAcme\Api;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AccountDetailsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('account:details')
            ->setDescription('Show account details.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var Api $client */
        $client = $this->getApplication()->resolve('acmeClient');
        $account = $client->account()->get();

        $output->writeln('Account details.');
        $output->write('ID: ');
        $output->writeln($account->id);
        $output->write('Status: ');
        $output->writeln($account->status);
        $output->write('E-mail: ');
        $output->writeln($account->contact[0]);
        $output->write('Initial IP: ');
        $output->writeln($account->initialIp);
        $output->write('Created at: ');
        $output->writeln($account->createdAt);

        return Command::SUCCESS;
    }
}

<?php

namespace Rogierw\RwAcmeCli\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OrderCertificateCommand extends AbstractOrderCertificateCommand
{
    protected function configure(): void
    {
        $this
            ->setName('certificate:order')
            ->setDescription('Request a new LE certificate.');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->handleOrderProcess($input, $output);
    }
}

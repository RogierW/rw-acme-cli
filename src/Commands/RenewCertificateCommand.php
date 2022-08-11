<?php

namespace Rogierw\RwAcmeCli\Commands;

use Carbon\Carbon;
use Exception;
use Spatie\SslCertificate\SslCertificate;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RenewCertificateCommand extends AbstractOrderCertificateCommand
{
    protected function configure(): void
    {
        $this
            ->setName('certificate:renew')
            ->setDescription('Renew a LE certificate.');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $input->getArgument('domain');

        try {
            $certificate = SslCertificate::createForHostName($domain);
        } catch (Exception $e) {
            $output->writeln('<error>Domain has currently no certificate.</error>');

            return Command::FAILURE;
        }

        if (! $certificate->expirationDate() instanceof Carbon) {
            $output->writeln('<error>Invalid expiration date.</error>');

            return Command::FAILURE;
        }

        if ($certificate->expirationDate()->diffInDays() >= 60) {
            $output->writeln('This certificate is still valid. No need to extend this certificate.');

            return Command::SUCCESS;
        }

        $output->writeln("<comment>Expiration date is {$certificate->expirationDate()->format('d-m-Y H:i')}.</comment>");

        return $this->handleOrderProcess($input, $output);
    }
}

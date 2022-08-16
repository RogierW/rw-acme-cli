<?php

namespace Rogierw\RwAcmeCli\Commands;

use Exception;
use Rogierw\RwAcmeCli\Actions\FetchCertificateFromHostAction;
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
        $domain = $this->getSan($input)[0];

        try {
            $certificate = (new FetchCertificateFromHostAction)->execute($domain);
        } catch (Exception) {
            $this->writeError($output, 'Domain has currently no certificate.');

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

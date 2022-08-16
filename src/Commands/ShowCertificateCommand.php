<?php

namespace Rogierw\RwAcmeCli\Commands;

use Exception;
use Rogierw\RwAcmeCli\Actions\FetchCertificateFromHostAction;
use Rogierw\RwAcmeCli\Actions\VerifyHostAction;
use Rogierw\RwAcmeCli\Concerns\HasAlerts;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCertificateCommand extends Command
{
    use HasAlerts;

    protected function configure(): void
    {
        $this
            ->setName('certificate:show')
            ->setDescription('Show current certificate of a domain.')
            ->addArgument('domain', InputArgument::REQUIRED, 'For which domain do you want to fetch the SSL certificate?')
            ->addOption('vertically', 'x', InputOption::VALUE_NONE, 'Table contents are displayed vertically')
            ->addOption('extra-data', 'd', InputOption::VALUE_NONE, 'Show additional data about the certificate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $input->getArgument('domain');

        try {
            $certificate = (new FetchCertificateFromHostAction())->execute($domain);
        } catch (RuntimeException $e) {
            $this->writeError($output, $e->getMessage());

            return Command::FAILURE;
        } catch (Exception) {
            $certificate = (new FetchCertificateFromHostAction())->execute(hostName: $domain, verifyCertificate: false);

            try {
                (new VerifyHostAction())->execute($domain, $certificate);
            } catch (Exception $e) {
                $this->writeError($output, $e->getMessage());

                return Command::FAILURE;
            }
        }

        if ($certificate->isExpired()) {
            $this->writeWarning($output, 'Heads up! This certificate is expired.');
        }

        $headers = [
            'Issuer',
            'SAN',
            'Is currently valid',
            'Valid from',
            'Valid until',
        ];

        $rows = [
            $certificate->getIssuer(),
            implode(',', $certificate->getAdditionalDomains()),
            $certificate->isValid() ? 'YES' : '<error>  NO  </error>',
            $certificate->validFromDate(),
            $certificate->expirationDate(),
        ];

        if ($input->getOption('extra-data')) {
            $headers[] = 'Days until expiration';
            $headers[] = 'Fingerprint';
            $headers[] = 'Signature algorithm';
            $headers[] = 'Organization';

            $rows[] = $certificate->daysUntilExpirationDate();
            $rows[] = $certificate->getFingerprint();
            $rows[] = $certificate->getSignatureAlgorithm();
            $rows[] = $certificate->getOrganization();
        }

        $table = (new Table($output))->setHeaders($headers)->setRows([$rows]);

        if ($input->getOption('vertically')) {
            $table->setVertical();
        }

        $table->render();

        return Command::SUCCESS;
    }
}

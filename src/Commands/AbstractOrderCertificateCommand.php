<?php

namespace Rogierw\RwAcmeCli\Commands;

use Rogierw\RwAcme\Api;
use Rogierw\RwAcme\DTO\AccountData;
use Rogierw\RwAcme\DTO\DomainValidationData;
use Rogierw\RwAcme\DTO\OrderData;
use Rogierw\RwAcme\Exceptions\DomainValidationException;
use Rogierw\RwAcme\Support\OpenSsl;
use Rogierw\RwAcmeCli\Concerns\HasQuestions;
use Rogierw\RwAcmeCli\Support\File;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AbstractOrderCertificateCommand extends Command
{
    use HasQuestions;

    protected ?Api $acmeClient;
    protected ?AccountData $accountData;

    protected ?SymfonyStyle $io;

    protected function configure(): void
    {
        $this
            ->addArgument('domain', InputArgument::REQUIRED, 'For which domain do you want a SSL certificate?')
            ->addOption('include-www', null, InputOption::VALUE_NONE, 'Include the www. subdomain')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'An existing LE request ID');
    }

    protected function getAcmeClient(): Api
    {
        return $this->acmeClient ?? $this->getApplication()->resolve('acmeClient');
    }

    protected function getSan(InputInterface $input): array
    {
        $san = [$input->getArgument('domain')];

        if ($input->getOption('include-www')) {
            $san[] = 'www.' . $san[0];
        }

        return $san;
    }

    protected function getOrderData(array $san, ?string $id = null): OrderData
    {
        $acmeClient = $this->getAcmeClient();

        if (is_numeric(trim($id ?? ''))) {
            return $acmeClient->order()->get(trim($id));
        }

        return $acmeClient->order()->new($this->accountData, $san);
    }

    protected function displayValidationDetails(OrderData $orderData, OutputInterface $output): void
    {
        $acmeClient = $this->getAcmeClient();

        /** @var DomainValidationData[] $domainValidation */
        $domainValidation = $acmeClient->domainValidation()->status($orderData);

        foreach ($domainValidation as $domainValidationData) {
            $identifier = $domainValidationData->identifier['value'];

            if ($domainValidationData->isValid()) {
                $output->writeln("File validation completed for {$identifier}.");
                $output->writeln('Validated at: ' . $domainValidationData->file['validated']);

                continue;
            }

            $validationDetails = $acmeClient->domainValidation()->getFileValidationData([$domainValidationData])[0];

            $output->writeln("Validation details of {$identifier}");
            $output->writeln('Method: http-01');
            $output->write('Status: ');
            $output->writeln($domainValidationData->status);
            $output->write('Expires: ');
            $output->writeln($domainValidationData->expires);
            $output->write('Filename: ');
            $output->writeln($validationDetails['filename']);
            $output->write('Content: ');
            $output->writeln($validationDetails['content']);
            $this->io->newLine();
        }
    }

    protected function handleOrderProcess(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $san = $this->getSan($input);
        $domain = $san[0];

        $acmeClient = $this->getAcmeClient();
        $this->accountData = $acmeClient->account()->get();

        $orderData = $this->getOrderData($san, $input->getOption('id'));
        $id = $orderData->id;

        $output->writeln("<comment>Order ID is {$id}.</comment>");
        $this->io->newLine();

        $this->displayValidationDetails($orderData, $output);

        if ($orderData->isPending()) {
            if (! $this->ask('Do you want to start the file validation? ', $input, $output)) {
                return Command::SUCCESS;
            }

            $this->startDcv($orderData, $input, $output);
        }

        $orderData = $acmeClient->order()->get($id);

        if (! $orderData->isReady()) {
            $output->writeln("<comment>Order status: {$orderData->status}.</comment>");
            $this->io->newLine();
            $output->writeln('<error>Unexpected status. Abort renew process.</error>');
            $this->io->newLine();

            return Command::FAILURE;
        }

        $filePostfix = date('Y-m-d-His');

        $privateKey = OpenSsl::generatePrivateKey();
        $csr = OpenSsl::generateCsr($san, $privateKey);

        $this->writeToFile(
            storage_path(sprintf('pem_files' . DIRECTORY_SEPARATOR . 'privkey_%s_%s.pem', $domain, $filePostfix)),
            $privateKey,
        );
        $this->writeToFile(
            storage_path(
            sprintf('pem_files' . DIRECTORY_SEPARATOR . 'csr_%s_%s.pem', $domain, $filePostfix)
        ),
            $csr,
        );

        if ($orderData->isNotFinalized()) {
            if (! $acmeClient->order()->finalize($orderData, $csr)) {
                $output->writeln('<error>Order could not be finalized.</error>');

                return Command::FAILURE;
            }
        }

        if ($orderData->isFinalized()) {
            $output->writeln('<info>The order is successfully finalized.</info>');

            $certificateBundle = $acmeClient->certificate()->getBundle($orderData);

            $this->writeToFile(
                storage_path(vsprintf('pem_files%scertificate_%s_%s.pem', [
                    DIRECTORY_SEPARATOR,
                    $domain,
                    $filePostfix,
                ])),
                $certificateBundle->certificate
            );
            $this->writeToFile(
                storage_path(vsprintf('pem_files%sfullchain_%s_%s.pem', [
                    DIRECTORY_SEPARATOR,
                    $domain,
                    $filePostfix,
                ])),
                $certificateBundle->fullchain
            );

            $output->writeln('<info>PEM files are successfully stored in ' . storage_path('pem_files') . '.</info>');

            return Command::SUCCESS;
        }

        $output->writeln("<comment>Order status is {$orderData->status}.</comment>");
        $output->writeln('<error>Order is not finalized.</error>');

        return Command::FAILURE;
    }

    protected function startDcv(OrderData $orderData, InputInterface $input, OutputInterface $output): void
    {
        $acmeClient = $this->getAcmeClient();
        $domainValidation = $acmeClient->domainValidation()->status($orderData);

        foreach ($domainValidation as $domainValidationData) {
            try {
                startValidation:

                $acmeClient->domainValidation()->start($this->accountData, $domainValidationData);
            } catch (DomainValidationException $exception) {
                $output->writeln("<error>{$exception->getMessage()}</error>");
                $this->io->newLine();

                if ($this->ask('Do you want to retry the local test? ', $input, $output)) {
                    goto startValidation;
                }
            }
        }
    }

    protected function writeToFile(string $file, string $content): void
    {
        File::write($file, $content);
    }
}

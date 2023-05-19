<?php

namespace Rogierw\RwAcmeCli\Commands;

use Rogierw\RwAcme\Api;
use Rogierw\RwAcme\DTO\AccountData;
use Rogierw\RwAcme\DTO\DomainValidationData;
use Rogierw\RwAcme\DTO\OrderData;
use Rogierw\RwAcme\Enums\AuthorizationChallengeEnum;
use Rogierw\RwAcme\Exceptions\DomainValidationException;
use Rogierw\RwAcme\Support\OpenSsl;
use Rogierw\RwAcmeCli\Concerns\HasAlerts;
use Rogierw\RwAcmeCli\Concerns\HasQuestions;
use Rogierw\RwAcmeCli\Support\File;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AbstractOrderCertificateCommand extends Command
{
    use HasQuestions;
    use HasAlerts;

    protected ?Api $acmeClient = null;
    protected ?AccountData $accountData;
    protected AuthorizationChallengeEnum $authChallenge = AuthorizationChallengeEnum::HTTP;

    protected ?SymfonyStyle $io;

    protected function configure(): void
    {
        $this
            ->addArgument('domain', InputArgument::REQUIRED, 'For which domain do you want a SSL certificate?')
            ->addOption('dcv-dns', 'd', InputOption::VALUE_NONE, 'Set DCV challenge to dns-01')
            ->addOption('include-www', 'w', InputOption::VALUE_NONE, 'Include the www. subdomain')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'An existing LE request ID');
    }

    protected function getAcmeClient(): Api
    {
        if (is_null($this->acmeClient)) {
            $this->acmeClient = $this->getApplication()->resolve('acmeClient');
        }

        return $this->acmeClient;
    }

    protected function getSan(InputInterface $input): array
    {
        $san = [$input->getArgument('domain')];

        if (! str_contains($san[0], '.') || str_starts_with($san[0], '.') || str_ends_with($san[0], '.')) {
            throw new RuntimeException("`{$san[0]}` is not a valid domain.");
        }

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
        $acmeClient = $this->getAcmeClient()->setLogger(new ConsoleLogger($output));

        /** @var DomainValidationData[] $domainValidation */
        $domainValidation = $acmeClient->domainValidation()->status($orderData);

        foreach ($domainValidation as $domainValidationData) {
            $identifier = $domainValidationData->identifier['value'];
            $type = $this->authChallenge === AuthorizationChallengeEnum::HTTP ? 'file' : 'dns';

            if ($domainValidationData->isValid()) {
                $output->writeln("Domain validation completed for {$identifier}.");
                $output->writeln('Validated at: ' . $domainValidationData->{$type}['validated']);

                continue;
            }

            $validationDetails = $acmeClient
                ->domainValidation()
                ->getValidationData([$domainValidationData], $this->authChallenge);

            $this->io->title("Validation details of {$identifier}");

            foreach ($validationDetails as $challengeData) {
                if ($challengeData['type'] !== $this->authChallenge->value) {
                    continue;
                }

                $output->writeln('Domain: ' . $challengeData['identifier']);
                $output->writeln('Method: ' . $challengeData['type']);
                $output->writeln('Status: ' . $domainValidationData->{$type}['status']);
                $output->writeln('Expires: ' . $domainValidationData->expires);

                if (isset($domainValidationData->{$type}['error']['detail'])) {
                    $output->writeln('Error: ' . $domainValidationData->{$type}['error']['detail']);
                }

                if ($challengeData['type'] === AuthorizationChallengeEnum::DNS->value
                    && $this->authChallenge === AuthorizationChallengeEnum::DNS
                ) {
                    $output->writeln('Record type: TXT');
                    $output->writeln('Name: ' . $challengeData['name']);
                    $output->writeln('Value: ' . $challengeData['value']);
                } else {
                    $output->writeln('Filename: ' . $challengeData['filename']);
                    $output->writeln('Content: ' . $challengeData['content']);
                }

                $this->io->newLine();
            }
        }
    }

    protected function handleOrderProcess(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        try {
            $san = $this->getSan($input);
        } catch (RuntimeException $e) {
            $this->writeError($output, $e->getMessage());

            return Command::FAILURE;
        }

        $domain = $san[0];

        $acmeClient = $this->getAcmeClient()->setLogger(new ConsoleLogger($output));
        $this->accountData = $acmeClient->account()->get();

        if ($input->getOption('dcv-dns')) {
            $this->authChallenge = AuthorizationChallengeEnum::DNS;
        }

        $orderData = $this->getOrderData($san, $input->getOption('id'));
        $id = $orderData->id;

        $output->writeln("<comment>Order ID is {$id}.</comment>");
        $output->writeln("<comment>Authorization challenge is {$this->authChallenge->value}.</comment>");
        $this->io->newLine();

        $this->displayValidationDetails($orderData, $output);

        if ($orderData->isPending()) {
            if (! $this->ask('Do you want to start the domain validation? ', $input, $output)) {
                return Command::SUCCESS;
            }

            $this->startDcv($orderData, $input, $output);
        }

        $orderData = $acmeClient->order()->get($id);

        if (! $orderData->isReady()) {
            $output->writeln("<comment>Order status: {$orderData->status}.</comment>");
            $this->io->newLine();

            $this->writeError($output, 'Unexpected status. Abort...');
            $this->io->newLine();

            return Command::FAILURE;
        }

        $filePostfix = date('Y-m-d-His');

        $privateKey = OpenSsl::generatePrivateKey();
        $csr = OpenSsl::generateCsr($san, $privateKey);

        $this->writeToFile(
            storage_path(sprintf('pem_files' . DIRECTORY_SEPARATOR . 'privkey_%s_%s.pem', $domain, $filePostfix)),
            OpenSsl::openSslKeyToString($privateKey),
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
            $this->io->newLine();
            $output->writeln('<info>The order is successfully finalized.</info>');

            $certificateBundle = $acmeClient->certificate()->getBundle($orderData);

            $this->writeToFile(
                storage_path(vsprintf('pem_files%sorder_id_%s_%s.txt', [
                    DIRECTORY_SEPARATOR,
                    $domain,
                    $filePostfix,
                ])),
                $orderData->id
            );
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
        $acmeClient = $this->getAcmeClient()->setLogger(new ConsoleLogger($output));
        $domainValidation = $acmeClient->domainValidation()->status($orderData);

        foreach ($domainValidation as $domainValidationData) {
            try {
                startValidation:

                $acmeClient->domainValidation()->start($this->accountData, $domainValidationData, $this->authChallenge);
            } catch (DomainValidationException $exception) {
                $output->writeln("<error>{$exception->getMessage()}</error>");
                $this->io->newLine();

                if ($this->ask('Do you want to retry the local test? ', $input, $output)) {
                    goto startValidation;
                }
            }
        }

        if ($acmeClient->domainValidation()->allChallengesPassed($orderData)) {
            $output->writeln('Domain validation has been completed.');
        } else {
            $this->writeError($output, "Domain validation hasn't been completed.");
        }

        $this->io->newLine();
        $this->displayValidationDetails($orderData, $output);
    }

    protected function writeToFile(string $file, string $content): void
    {
        File::write($file, $content);
    }
}

<?php

namespace Rogierw\RwAcmeCli\Commands;

use Exception;
use Rogierw\RwAcme\Api;
use Rogierw\RwAcmeCli\Concerns\HasAlerts;
use Rogierw\RwAcmeCli\Concerns\HasQuestions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RevokeCertificateCommand extends Command
{
    use HasQuestions;
    use HasAlerts;

    protected function configure(): void
    {
        $this
            ->setName('certificate:revoke')
            ->setDescription('Revoke a certificate.')
            ->addArgument(
                'order ID',
                InputArgument::REQUIRED,
                'The order ID of the certificate which you want to revoke.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = trim($input->getArgument('order ID'));

        if (! is_numeric($id)) {
            $this->writeError($output, "Order ID `{$id}` is not valid.");

            return Command::FAILURE;
        }

        /** @var Api $client */
        $client = $this->getApplication()->resolve('acmeClient');

        try {
            $order = $client->order()->get($id);
        } catch (Exception $e) {
            $this->writeError($output, $e->getMessage());

            return Command::FAILURE;
        }

        (new Table($output))
            ->setHeaders([
                'id',
                'status',
                'expires',
                'domain',
            ])
            ->setRows([
                [
                    $order->id,
                    $order->status,
                    $order->expires,
                    $order->identifiers[0]['value'],
                ],
            ])
            ->render();

        $io = new SymfonyStyle($input, $output);

        $io->newLine();

        if ($this->ask('Are you sure you want to revoke this certificate? ', $input, $output)) {
            $bundle = $client->certificate()->getBundle($order);

            $io->newLine();

            if ($client->certificate()->revoke($bundle->fullchain)) {
                $this->writeSuccess($output, 'LE certificate successfully revoked.');

                return Command::SUCCESS;
            }

            $this->writeError($output, 'Failed to revoke certificate.');

            return Command::FAILURE;
        }

        $io->newLine();
        $output->writeln(PHP_EOL . '<comment>Revocation aborted.</comment>');

        return Command::SUCCESS;
    }
}

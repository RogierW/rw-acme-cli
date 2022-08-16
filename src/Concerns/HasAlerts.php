<?php

namespace Rogierw\RwAcmeCli\Concerns;

use Symfony\Component\Console\Output\OutputInterface;

trait HasAlerts
{
    protected function writeError(OutputInterface $output, string $message): void
    {
        $output->writeln($this->getHelper('formatter')->formatBlock($message, 'error', true));
    }

    protected function writeWarning(OutputInterface $output, string $message): void
    {
        $output->writeln($this->getHelper('formatter')->formatBlock($message, 'fg=#664d03;bg=#fff3cd', true));
    }
}

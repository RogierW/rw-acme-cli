<?php

namespace Rogierw\RwAcmeCli\Concerns;

use Symfony\Component\Console\Output\OutputInterface;

trait HasAlerts
{
    protected function writeSuccess(OutputInterface $output, string $message): void
    {
        $output->writeln($this->getHelper('formatter')->formatBlock($message, 'fg=#0f5132;bg=#badbcc', true));
    }

    protected function writeWarning(OutputInterface $output, string $message): void
    {
        $output->writeln($this->getHelper('formatter')->formatBlock($message, 'fg=#664d03;bg=#fff3cd', true));
    }

    protected function writeError(OutputInterface $output, string $message): void
    {
        $output->writeln($this->getHelper('formatter')->formatBlock($message, 'error', true));
    }
}

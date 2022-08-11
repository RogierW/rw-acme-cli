<?php

namespace Rogierw\RwAcmeCli\Concerns;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

trait HasQuestions
{
    protected function ask(string $question, InputInterface $input, OutputInterface $output): bool
    {
        $helper = $this->getHelper('question');
        $confirmationQuestion = new ConfirmationQuestion($question, false);

        return $helper->ask($input, $output, $confirmationQuestion);
    }
}
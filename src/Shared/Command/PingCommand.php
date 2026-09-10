<?php

declare(strict_types=1);

namespace App\Shared\Command;

use App\Shared\Message\PingMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:ping', description: 'Dispatch a PingMessage to the async transport')]
final class PingCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('text', InputArgument::OPTIONAL, 'Text to echo', 'ping');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bus->dispatch(new PingMessage((string) $input->getArgument('text')));
        $output->writeln('Dispatched.');

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\MessageHandler;

use App\Shared\Message\PingMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PingMessageHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(PingMessage $message): void
    {
        $this->logger->info('PONG: {text}', ['text' => $message->text]);
    }
}

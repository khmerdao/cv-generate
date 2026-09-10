<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Message\PingMessage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class MessengerTest extends KernelTestCase
{
    public function testPingIsRoutedToAsyncTransport(): void
    {
        self::bootKernel();
        static::getContainer()->get(MessageBusInterface::class)->dispatch(new PingMessage('hi'));

        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        self::assertCount(1, $transport->getSent());
    }
}

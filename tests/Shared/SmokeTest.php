<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SmokeTest extends WebTestCase
{
    #[DataProvider('publicUrls')]
    public function testPublicPagesRespond(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
    }

    /** @return iterable<array{string}> */
    public static function publicUrls(): iterable
    {
        yield ['/fr/'];
        yield ['/en/'];
        yield ['/fr/login'];
        yield ['/fr/register'];
        yield ['/fr/reset-password'];
    }
}

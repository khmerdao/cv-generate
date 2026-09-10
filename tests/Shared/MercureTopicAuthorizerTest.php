<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Auth\Entity\User;
use App\Shared\Mercure\MercureTopicAuthorizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

final class MercureTopicAuthorizerTest extends KernelTestCase
{
    public function testCookieJwtOnlyContainsRequestedTopics(): void
    {
        self::bootKernel();
        $request = Request::create('/fr/app');
        static::getContainer()->get(RequestStack::class)->push($request);

        $authorizer = static::getContainer()->get(MercureTopicAuthorizer::class);
        $id = Uuid::v7();
        $authorizer->subscribeCookieFor(new User('j@example.com'), [$authorizer->topicFor($id)]);

        $cookies = array_values($request->attributes->get('_mercure_authorization_cookies', []));
        self::assertCount(1, $cookies);
        [, $payload] = explode('.', (string) $cookies[0]->getValue());
        $claims = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);
        self::assertSame(['/tailorings/'.$id], $claims['mercure']['subscribe']);
        self::assertArrayNotHasKey('publish', $claims['mercure']);
    }
}

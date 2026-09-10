<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\Entity\User;
use App\Tests\Support\AuthenticatedWebTestCase;
use Symfony\Component\Mime\Email;

final class VerifyEmailTest extends AuthenticatedWebTestCase
{
    public function testVerificationLinkMarksUserVerified(): void
    {
        $client = static::createClient();
        $user = $this->createUser(verified: false);
        $this->loginAs($client, $user);

        $client->request('POST', '/fr/verify-email/resend');
        self::assertResponseRedirects('/fr/app');
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        if (1 !== preg_match('#href="([^"]+verify-email[^"]+)"#', (string) $email->getHtmlBody(), $m)) {
            self::fail('No verification link found in the email body.');
        }

        $client->request('GET', html_entity_decode($m[1]));
        self::assertResponseRedirects('/fr/app');

        $fresh = $this->em()->find(User::class, $user->getId());
        self::assertTrue($fresh?->isVerified());
    }

    public function testTamperedLinkIsRejected(): void
    {
        $client = static::createClient();
        $user = $this->createUser(verified: false);
        $this->loginAs($client, $user);
        $client->request('GET', '/fr/verify-email?expires=1&signature=bad&token=bad');
        self::assertResponseRedirects('/fr/app');
        $client->followRedirect();
        self::assertSelectorExists('.flash-error');
    }
}

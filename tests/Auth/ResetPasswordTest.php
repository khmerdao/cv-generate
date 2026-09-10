<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Tests\Support\AuthenticatedWebTestCase;
use Symfony\Component\Mime\Email;

final class ResetPasswordTest extends AuthenticatedWebTestCase
{
    public function testFullResetFlow(): void
    {
        $client = static::createClient();
        $this->createUser();

        $client->request('GET', '/fr/reset-password');
        $client->submitForm('Envoyer', ['reset_password_request[email]' => 'jane@example.com']);
        self::assertResponseRedirects('/fr/reset-password/check-email');

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        if (1 !== preg_match('#href="([^"]+/reset-password/reset/[^"]+)"#', (string) $email->getHtmlBody(), $m)) {
            self::fail('No reset link found in the email body.');
        }

        $client->request('GET', html_entity_decode($m[1]));
        self::assertResponseRedirects('/fr/reset-password/reset');
        $client->followRedirect();
        $client->submitForm('Modifier le mot de passe', [
            'change_password[plainPassword][first]' => 'Brand-New-Pass-42',
            'change_password[plainPassword][second]' => 'Brand-New-Pass-42',
        ]);
        self::assertResponseRedirects('/fr/app');

        $client->request('GET', '/fr/logout');
        $client->request('GET', '/fr/login');
        $client->submitForm('Se connecter', ['_username' => 'jane@example.com', '_password' => 'Brand-New-Pass-42']);
        self::assertResponseRedirects('/fr/app');
    }

    public function testUnknownEmailStillRedirectsToCheckEmail(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fr/reset-password');
        $client->submitForm('Envoyer', ['reset_password_request[email]' => 'nobody@example.com']);
        self::assertResponseRedirects('/fr/reset-password/check-email');
        self::assertEmailCount(0);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Tests\Support\AuthenticatedWebTestCase;

final class LoginTest extends AuthenticatedWebTestCase
{
    public function testValidCredentialsRedirectToDashboard(): void
    {
        $client = static::createClient();
        $this->createUser();
        $client->request('GET', '/fr/login');
        $client->submitForm('Se connecter', ['_username' => 'jane@example.com', '_password' => self::PASSWORD]);
        self::assertResponseRedirects('/fr/app');
    }

    public function testInvalidPasswordShowsError(): void
    {
        $client = static::createClient();
        $this->createUser();
        $client->request('GET', '/fr/login');
        $client->submitForm('Se connecter', ['_username' => 'jane@example.com', '_password' => 'wrong']);
        self::assertResponseRedirects('/fr/login');
        $client->followRedirect();
        self::assertSelectorExists('.form-error');
    }

    public function testLoginIsThrottledAfterFiveFailures(): void
    {
        $client = static::createClient();
        $this->createUser();
        for ($i = 0; $i < 6; ++$i) {
            $client->request('GET', '/fr/login');
            $client->submitForm('Se connecter', ['_username' => 'jane@example.com', '_password' => 'wrong']);
        }
        $client->followRedirect();
        self::assertSelectorTextContains('.form-error', 'Trop de tentatives');
    }

    public function testDashboardRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fr/app');
        self::assertResponseRedirects('/fr/login');
    }
}

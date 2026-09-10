<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Auth\Entity\User;
use App\Tests\Support\AuthenticatedWebTestCase;

final class LocaleSubscriberTest extends AuthenticatedWebTestCase
{
    public function testAnonymousRedirectsByAcceptLanguage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login', server: ['HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9']);
        self::assertResponseRedirects('/en/login');
    }

    public function testAnonymousFallsBackToFrench(): void
    {
        $client = static::createClient();
        $client->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'de-DE']);
        self::assertResponseRedirects('/fr');
    }

    public function testLoggedInUserRedirectsToOwnLocale(): void
    {
        $client = static::createClient();
        $this->loginAs($client, $this->createUser(locale: 'en'));
        $client->request('GET', '/app', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);
        self::assertResponseRedirects('/en/app');
    }

    public function testSwitchPersistsLocaleOnUser(): void
    {
        $client = static::createClient();
        $user = $this->createUser(locale: 'fr');
        $this->loginAs($client, $user);
        $client->request('POST', '/fr/locale/en', ['_redirect' => '/fr/app']);
        self::assertResponseRedirects('/en/app');
        self::assertSame('en', $this->em()->find(User::class, $user->getId())?->getLocale());
    }
}

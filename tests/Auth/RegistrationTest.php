<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\Entity\User;
use App\Tests\Support\AuthenticatedWebTestCase;

final class RegistrationTest extends AuthenticatedWebTestCase
{
    public function testUserCanRegisterAndIsLoggedIn(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fr/register');
        self::assertResponseIsSuccessful();

        $client->submitForm('registration[submit]', [
            'registration[email]' => 'new@example.com',
            'registration[plainPassword]' => 'Correct-Horse-9',
        ]);
        self::assertResponseRedirects('/fr/app');

        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'new@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertFalse($user->isVerified());
        self::assertSame('fr', $user->getLocale());
        // The verification mail is routed to the async transport (see messenger.yaml).
        self::assertQueuedEmailCount(1);
    }

    public function testRegistrationRejectsShortPasswordAndDuplicateEmail(): void
    {
        $client = static::createClient();
        $this->createUser('taken@example.com');

        $client->request('GET', '/fr/register');
        $client->submitForm('registration[submit]', [
            'registration[email]' => 'taken@example.com',
            'registration[plainPassword]' => 'short',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.form-error');
    }
}

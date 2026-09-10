<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Auth\Entity\User;
use App\Billing\Entity\Subscription;
use App\Billing\Entity\UsageCounter;
use App\Resume\Entity\Resume;
use App\Tailoring\Entity\Tailoring;
use App\Tests\Support\AuthenticatedWebTestCase;

final class AccountTest extends AuthenticatedWebTestCase
{
    public function testChangePassword(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $this->loginAs($client, $user);

        $client->request('GET', '/fr/app/account');
        self::assertResponseIsSuccessful();
        $client->submitForm('Modifier le mot de passe', [
            'change_password[plainPassword][first]' => 'Another-Pass-77',
            'change_password[plainPassword][second]' => 'Another-Pass-77',
        ]);
        self::assertResponseRedirects('/fr/app/account');

        $client->request('GET', '/fr/logout');
        $client->request('GET', '/fr/login');
        $client->submitForm('Se connecter', ['_username' => 'jane@example.com', '_password' => 'Another-Pass-77']);
        self::assertResponseRedirects('/fr/app');
    }

    public function testDeleteAccountPurgesEverything(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $em = $this->em();
        $resume = new Resume($user, 'Main');
        $em->persist($resume);
        $em->persist(new Tailoring($user, $resume));
        $em->persist(new UsageCounter($user, '2026-09'));
        $em->flush();
        $userId = $user->getId();

        $this->loginAs($client, $user);
        $client->request('GET', '/fr/app/account');
        $client->submitForm('Supprimer mon compte', ['delete_account[confirmation]' => 'SUPPRIMER']);
        self::assertResponseRedirects('/fr/');

        $em->clear();
        self::assertNull($em->find(User::class, $userId));
        self::assertCount(0, $em->getRepository(Resume::class)->findAll());
        self::assertCount(0, $em->getRepository(Tailoring::class)->findAll());
        self::assertCount(0, $em->getRepository(Subscription::class)->findAll());
        self::assertCount(0, $em->getRepository(UsageCounter::class)->findAll());
    }

    public function testDeleteRequiresConfirmationWord(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $this->loginAs($client, $user);
        $client->request('GET', '/fr/app/account');
        $client->submitForm('Supprimer mon compte', ['delete_account[confirmation]' => 'nope']);
        self::assertResponseStatusCodeSame(422);
        self::assertNotNull($this->em()->find(User::class, $user->getId()));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Auth\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class AuthenticatedWebTestCase extends WebTestCase
{
    public const string PASSWORD = 'Password-123!';

    protected function setUp(): void
    {
        parent::setUp();
        // Login throttling / rate-limiter state lives in a cache pool, not the
        // DB, so DAMA does not roll it back. Clear it so each test starts fresh.
        self::bootKernel();
        self::getContainer()->get('cache.rate_limiter')->clear();
        self::ensureKernelShutdown();
    }

    protected function createUser(string $email = 'jane@example.com', string $password = self::PASSWORD, bool $verified = true, string $locale = 'fr'): User
    {
        $container = static::getContainer();
        $user = new User($email, $locale);
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, $password));
        if ($verified) {
            $user->markVerified();
        }
        $em = $container->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    protected function loginAs(KernelBrowser $client, User $user): void
    {
        $client->loginUser($user);
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}

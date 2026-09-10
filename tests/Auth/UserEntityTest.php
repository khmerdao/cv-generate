<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\Entity\User;
use App\Billing\Enum\Plan;
use PHPUnit\Framework\TestCase;

final class UserEntityTest extends TestCase
{
    public function testNewUserIsUnverifiedWithFreeSubscription(): void
    {
        $user = new User('jane@example.com', 'en');

        self::assertFalse($user->isVerified());
        self::assertSame('en', $user->getLocale());
        self::assertSame(Plan::Free, $user->getSubscription()->getPlan());
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertSame($user, $user->getSubscription()->getUser());
    }

    public function testMarkVerifiedSetsTimestamp(): void
    {
        $user = new User('jane@example.com');
        $user->markVerified();
        self::assertTrue($user->isVerified());
    }
}

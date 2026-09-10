<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Auth\Entity\User;
use App\Auth\Security\VerifiedUserVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class VerifiedUserVoterTest extends TestCase
{
    public function testVerifiedUserGranted(): void
    {
        $user = new User('v@example.com');
        $user->markVerified();
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        self::assertSame(VoterInterface::ACCESS_GRANTED, new VerifiedUserVoter()->vote($token, null, ['IS_VERIFIED']));
    }

    public function testUnverifiedUserDenied(): void
    {
        $user = new User('u@example.com');
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        self::assertSame(VoterInterface::ACCESS_DENIED, new VerifiedUserVoter()->vote($token, null, ['IS_VERIFIED']));
    }
}

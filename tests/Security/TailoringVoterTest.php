<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Auth\Entity\User;
use App\Resume\Entity\Resume;
use App\Tailoring\Entity\Tailoring;
use App\Tailoring\Security\TailoringVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class TailoringVoterTest extends TestCase
{
    public function testOwnerIsGranted(): void
    {
        $owner = new User('o@example.com');
        $tailoring = new Tailoring($owner, new Resume($owner, 'x'));
        $token = new UsernamePasswordToken($owner, 'main', $owner->getRoles());

        foreach ([TailoringVoter::VIEW, TailoringVoter::EDIT, TailoringVoter::DELETE] as $attr) {
            self::assertSame(VoterInterface::ACCESS_GRANTED, new TailoringVoter()->vote($token, $tailoring, [$attr]));
        }
    }

    public function testOtherUserIsDenied(): void
    {
        $owner = new User('o@example.com');
        $tailoring = new Tailoring($owner, new Resume($owner, 'x'));
        $other = new User('x@example.com');
        $token = new UsernamePasswordToken($other, 'main', $other->getRoles());
        self::assertSame(VoterInterface::ACCESS_DENIED, new TailoringVoter()->vote($token, $tailoring, [TailoringVoter::VIEW]));
    }

    public function testAnonymousIsDenied(): void
    {
        $owner = new User('o@example.com');
        $tailoring = new Tailoring($owner, new Resume($owner, 'x'));
        self::assertSame(VoterInterface::ACCESS_DENIED, new TailoringVoter()->vote(new NullToken(), $tailoring, [TailoringVoter::VIEW]));
    }

    public function testAbstainsOnOtherSubjects(): void
    {
        $user = new User('o@example.com');
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, new TailoringVoter()->vote($token, new \stdClass(), [TailoringVoter::VIEW]));
    }
}

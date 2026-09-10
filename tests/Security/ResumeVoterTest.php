<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Auth\Entity\User;
use App\Resume\Entity\Resume;
use App\Resume\Security\ResumeVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ResumeVoterTest extends TestCase
{
    public function testOwnerIsGranted(): void
    {
        $owner = new User('o@example.com');
        $resume = new Resume($owner, 'x');
        $token = new UsernamePasswordToken($owner, 'main', $owner->getRoles());

        foreach ([ResumeVoter::VIEW, ResumeVoter::EDIT, ResumeVoter::DELETE] as $attr) {
            self::assertSame(VoterInterface::ACCESS_GRANTED, new ResumeVoter()->vote($token, $resume, [$attr]));
        }
    }

    public function testOtherUserIsDenied(): void
    {
        $resume = new Resume(new User('o@example.com'), 'x');
        $other = new User('x@example.com');
        $token = new UsernamePasswordToken($other, 'main', $other->getRoles());
        self::assertSame(VoterInterface::ACCESS_DENIED, new ResumeVoter()->vote($token, $resume, [ResumeVoter::VIEW]));
    }

    public function testAnonymousIsDenied(): void
    {
        $resume = new Resume(new User('o@example.com'), 'x');
        self::assertSame(VoterInterface::ACCESS_DENIED, new ResumeVoter()->vote(new NullToken(), $resume, [ResumeVoter::VIEW]));
    }

    public function testAbstainsOnOtherSubjects(): void
    {
        $user = new User('o@example.com');
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, new ResumeVoter()->vote($token, new \stdClass(), [ResumeVoter::VIEW]));
    }
}

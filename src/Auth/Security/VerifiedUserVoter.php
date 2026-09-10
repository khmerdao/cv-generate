<?php

declare(strict_types=1);

namespace App\Auth\Security;

use App\Auth\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, mixed> */
final class VerifiedUserVoter extends Voter
{
    public const string IS_VERIFIED = 'IS_VERIFIED';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::IS_VERIFIED === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $user->isVerified();
    }
}

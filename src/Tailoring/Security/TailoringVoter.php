<?php

declare(strict_types=1);

namespace App\Tailoring\Security;

use App\Auth\Entity\User;
use App\Tailoring\Entity\Tailoring;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Tailoring> */
final class TailoringVoter extends Voter
{
    public const string VIEW = 'TAILORING_VIEW';
    public const string EDIT = 'TAILORING_EDIT';
    public const string DELETE = 'TAILORING_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true) && $subject instanceof Tailoring;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $subject->getUser()->getId()->equals($user->getId());
    }
}

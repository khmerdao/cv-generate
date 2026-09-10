<?php

declare(strict_types=1);

namespace App\Resume\Security;

use App\Auth\Entity\User;
use App\Resume\Entity\Resume;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Resume> */
final class ResumeVoter extends Voter
{
    public const string VIEW = 'RESUME_VIEW';
    public const string EDIT = 'RESUME_EDIT';
    public const string DELETE = 'RESUME_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true) && $subject instanceof Resume;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $subject->getUser()->getId()->equals($user->getId());
    }
}

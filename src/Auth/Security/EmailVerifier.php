<?php

declare(strict_types=1);

namespace App\Auth\Security;

use App\Auth\Entity\User;

/**
 * Minimal stub; Task 6 replaces this with the real verify-email flow.
 */
final class EmailVerifier
{
    public function sendConfirmation(User $user): void
    {
    }
}

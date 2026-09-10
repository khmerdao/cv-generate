<?php

declare(strict_types=1);

namespace App\Tests\Tailoring;

use App\Auth\Entity\User;
use App\JobOffer\Entity\JobOffer;
use App\Resume\Entity\Resume;
use App\Tailoring\Entity\Tailoring;
use App\Tailoring\Enum\TailoringStatus;
use PHPUnit\Framework\TestCase;

final class TailoringEntityTest extends TestCase
{
    public function testNewTailoringIsPendingWithClassicTheme(): void
    {
        $user = new User('jane@example.com');
        $resume = new Resume($user, 'Dev profile');
        $tailoring = new Tailoring($user, $resume);

        self::assertSame(TailoringStatus::Pending, $tailoring->getStatus());
        self::assertSame('classic', $tailoring->getTheme());
        self::assertNull($tailoring->getJobOffer());
        self::assertFalse($tailoring->getStatus()->isTerminal());
    }

    public function testFailStoresMessageAndIsTerminal(): void
    {
        $user = new User('jane@example.com');
        $tailoring = new Tailoring($user, new Resume($user, 'x'));
        $tailoring->fail('Boom');

        self::assertSame(TailoringStatus::Failed, $tailoring->getStatus());
        self::assertSame('Boom', $tailoring->getErrorMessage());
        self::assertTrue($tailoring->getStatus()->isTerminal());
    }

    public function testJobOfferUrlHashIsNormalised(): void
    {
        self::assertSame(JobOffer::hashUrl('HTTPS://Example.com/jobs/1/?utm_source=x'), JobOffer::hashUrl('https://example.com/jobs/1'));
    }
}

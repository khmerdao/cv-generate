<?php

declare(strict_types=1);

namespace App\Billing\Entity;

use App\Auth\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'usage_counters')]
#[ORM\UniqueConstraint(name: 'uniq_usage_user_period', fields: ['user', 'periodKey'])]
class UsageCounter
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 7)]
    private string $periodKey;

    #[ORM\Column]
    private int $tailoringsCount = 0;

    public function __construct(User $user, string $periodKey)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->periodKey = $periodKey;
    }

    public static function periodKeyFor(\DateTimeInterface $date): string
    {
        return $date->format('Y-m');
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPeriodKey(): string
    {
        return $this->periodKey;
    }

    public function getTailoringsCount(): int
    {
        return $this->tailoringsCount;
    }

    public function increment(): void
    {
        ++$this->tailoringsCount;
    }

    public function decrement(): void
    {
        $this->tailoringsCount = max(0, $this->tailoringsCount - 1);
    }
}

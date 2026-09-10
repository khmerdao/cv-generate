<?php

declare(strict_types=1);

namespace App\Billing\Entity;

use App\Auth\Entity\User;
use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'subscriptions')]
class Subscription
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\OneToOne(inversedBy: 'subscription', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(enumType: Plan::class)]
    private Plan $plan = Plan::Free;

    #[ORM\Column(enumType: SubscriptionStatus::class)]
    private SubscriptionStatus $status = SubscriptionStatus::Active;

    #[ORM\Column(nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(nullable: true)]
    private ?string $stripeSubscriptionId = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $currentPeriodEnd = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPlan(): Plan
    {
        return $this->plan;
    }

    public function getStatus(): SubscriptionStatus
    {
        return $this->status;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function getCurrentPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->currentPeriodEnd;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(Plan $plan, SubscriptionStatus $status, ?string $stripeCustomerId, ?string $stripeSubscriptionId, ?\DateTimeImmutable $currentPeriodEnd): void
    {
        $this->plan = $plan;
        $this->status = $status;
        $this->stripeCustomerId = $stripeCustomerId;
        $this->stripeSubscriptionId = $stripeSubscriptionId;
        $this->currentPeriodEnd = $currentPeriodEnd;
        $this->updatedAt = new \DateTimeImmutable();
    }
}

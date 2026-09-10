<?php

declare(strict_types=1);

namespace App\Tailoring\Entity;

use App\Auth\Entity\User;
use App\JobOffer\Entity\JobOffer;
use App\Resume\Entity\Resume;
use App\Tailoring\Enum\TailoringStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'tailorings')]
class Tailoring
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tailorings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Resume::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Resume $resume;

    #[ORM\ManyToOne(targetEntity: JobOffer::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JobOffer $jobOffer = null;

    #[ORM\Column(enumType: TailoringStatus::class)]
    private TailoringStatus $status = TailoringStatus::Pending;

    /** @var array<string, mixed>|null */
    #[ORM\Column(nullable: true)]
    private ?array $tailoredData = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $matchScore = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(nullable: true)]
    private ?array $changes = null;

    #[ORM\Column(length: 30)]
    private string $theme;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(nullable: true)]
    private ?array $llmUsage = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct(User $user, Resume $resume, string $theme = 'classic')
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->resume = $resume;
        $this->theme = $theme;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getResume(): Resume
    {
        return $this->resume;
    }

    public function getJobOffer(): ?JobOffer
    {
        return $this->jobOffer;
    }

    public function setJobOffer(?JobOffer $jobOffer): void
    {
        $this->jobOffer = $jobOffer;
        $this->touch();
    }

    public function getStatus(): TailoringStatus
    {
        return $this->status;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function setTheme(string $theme): void
    {
        $this->theme = $theme;
        $this->touch();
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getMatchScore(): ?int
    {
        return $this->matchScore;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    /** @return array<string, mixed>|null */
    public function getTailoredData(): ?array
    {
        return $this->tailoredData;
    }

    /** @return array<string, mixed>|null */
    public function getChanges(): ?array
    {
        return $this->changes;
    }

    /** @return array<string, mixed>|null */
    public function getLlmUsage(): ?array
    {
        return $this->llmUsage;
    }

    public function transitionTo(TailoringStatus $status): void
    {
        $this->status = $status;
        $this->touch();
        if (TailoringStatus::Done === $status) {
            $this->completedAt = new \DateTimeImmutable();
        }
    }

    public function fail(string $message): void
    {
        $this->errorMessage = $message;
        $this->transitionTo(TailoringStatus::Failed);
    }

    /**
     * @param array<string, mixed> $tailoredData
     * @param array<string, mixed> $changes
     * @param array<string, mixed> $llmUsage
     */
    public function complete(array $tailoredData, array $changes, int $matchScore, array $llmUsage): void
    {
        $this->tailoredData = $tailoredData;
        $this->changes = $changes;
        $this->matchScore = max(0, min(100, $matchScore));
        $this->llmUsage = $llmUsage;
        $this->errorMessage = null;
        $this->transitionTo(TailoringStatus::Done);
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

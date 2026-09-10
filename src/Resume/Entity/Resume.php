<?php

declare(strict_types=1);

namespace App\Resume\Entity;

use App\Auth\Entity\User;
use App\Resume\Repository\ResumeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ResumeRepository::class)]
#[ORM\Table(name: 'resumes')]
class Resume
{
    public const int CURRENT_SCHEMA_VERSION = 1;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'resumes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 120)]
    private string $title;

    /** @var array<string, mixed> */
    #[ORM\Column]
    private array $data = [];

    #[ORM\Column]
    private int $schemaVersion = self::CURRENT_SCHEMA_VERSION;

    #[ORM\Column(length: 5)]
    private string $language;

    #[ORM\Column(nullable: true)]
    private ?string $sourceFile = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user, string $title, string $language = 'fr')
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->title = $title;
        $this->language = $language;
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
        $this->touch();
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return $this->data;
    }

    /** @param array<string, mixed> $data */
    public function setData(array $data): void
    {
        $this->data = $data;
        $this->touch();
    }

    public function getSchemaVersion(): int
    {
        return $this->schemaVersion;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
        $this->touch();
    }

    public function getSourceFile(): ?string
    {
        return $this->sourceFile;
    }

    public function setSourceFile(?string $sourceFile): void
    {
        $this->sourceFile = $sourceFile;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

<?php

declare(strict_types=1);

namespace App\JobOffer\Entity;

use App\JobOffer\Enum\FetchStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'job_offers')]
#[ORM\UniqueConstraint(name: 'uniq_job_offers_url_hash', fields: ['urlHash'])]
class JobOffer
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $url;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $urlHash;

    #[ORM\Column(type: 'text')]
    private string $rawText;

    #[ORM\Column(nullable: true)]
    private ?string $title = null;

    #[ORM\Column(nullable: true)]
    private ?string $company = null;

    #[ORM\Column(nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $language = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(nullable: true)]
    private ?array $analysis = null;

    #[ORM\Column(enumType: FetchStatus::class)]
    private FetchStatus $fetchStatus = FetchStatus::Pending;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $fetchedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(?string $url, string $rawText = '')
    {
        $this->id = Uuid::v7();
        $this->url = $url;
        $this->urlHash = null === $url ? null : self::hashUrl($url);
        $this->rawText = $rawText;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** Lower-cases scheme and host, strips fragment, trailing slash and utm_* params. */
    public static function hashUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (false === $parts || !isset($parts['host'])) {
            return hash('sha256', trim($url));
        }
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $query = array_filter($query, static fn (int|string $k): bool => !str_starts_with((string) $k, 'utm_'), \ARRAY_FILTER_USE_KEY);
        ksort($query);
        $normalised = strtolower($parts['scheme'] ?? 'https').'://'.strtolower($parts['host'])
            .rtrim($parts['path'] ?? '/', '/')
            .([] === $query ? '' : '?'.http_build_query($query));

        return hash('sha256', $normalised);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getUrlHash(): ?string
    {
        return $this->urlHash;
    }

    public function getRawText(): string
    {
        return $this->rawText;
    }

    public function setRawText(string $rawText): void
    {
        $this->rawText = $rawText;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): void
    {
        $this->company = $company;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): void
    {
        $this->location = $location;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): void
    {
        $this->language = $language;
    }

    /** @return array<string, mixed>|null */
    public function getAnalysis(): ?array
    {
        return $this->analysis;
    }

    /** @param array<string, mixed> $analysis */
    public function setAnalysis(array $analysis): void
    {
        $this->analysis = $analysis;
    }

    public function getFetchStatus(): FetchStatus
    {
        return $this->fetchStatus;
    }

    public function setFetchStatus(FetchStatus $status): void
    {
        $this->fetchStatus = $status;
        if (FetchStatus::Fetched === $status || FetchStatus::Manual === $status) {
            $this->fetchedAt = new \DateTimeImmutable();
        }
    }

    public function getFetchedAt(): ?\DateTimeImmutable
    {
        return $this->fetchedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

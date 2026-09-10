<?php

declare(strict_types=1);

namespace App\Auth\Entity;

use App\Auth\Repository\UserRepository;
use App\Billing\Entity\Subscription;
use App\Resume\Entity\Resume;
use App\Tailoring\Entity\Tailoring;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column]
    private string $password = '';

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 2)]
    private string $locale;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Subscription::class, cascade: ['persist', 'remove'])]
    private ?Subscription $subscription = null;

    /** @var Collection<int, Resume> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Resume::class)]
    private Collection $resumes;

    /** @var Collection<int, Tailoring> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Tailoring::class)]
    private Collection $tailorings;

    public function __construct(string $email, string $locale = 'fr')
    {
        $this->id = Uuid::v7();
        $this->email = $email;
        $this->locale = $locale;
        $this->createdAt = new \DateTimeImmutable();
        $this->subscription = new Subscription($this);
        $this->resumes = new ArrayCollection();
        $this->tailorings = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        \assert('' !== $this->email);

        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSubscription(): Subscription
    {
        \assert($this->subscription instanceof Subscription);

        return $this->subscription;
    }

    /** @return Collection<int, Resume> */
    public function getResumes(): Collection
    {
        return $this->resumes;
    }

    /** @return Collection<int, Tailoring> */
    public function getTailorings(): Collection
    {
        return $this->tailorings;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function isVerified(): bool
    {
        return null !== $this->emailVerifiedAt;
    }

    public function markVerified(): void
    {
        $this->emailVerifiedAt = new \DateTimeImmutable();
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function eraseCredentials(): void
    {
    }
}

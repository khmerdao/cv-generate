<?php

declare(strict_types=1);

namespace App\Resume\Repository;

use App\Auth\Entity\User;
use App\Resume\Entity\Resume;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Resume> */
final class ResumeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Resume::class);
    }

    /** @return list<Resume> */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['updatedAt' => 'DESC']);
    }
}

<?php

namespace App\Repository;

use App\Entity\ArticleFeedback;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ArticleFeedback> */
class ArticleFeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleFeedback::class);
    }

    /** @return array<int, bool> articleId => interested */
    public function findMapForOwner(string $ownerKey): array    {
        $rows = $this->createQueryBuilder('f')
            ->select('IDENTITY(f.article) AS articleId, f.interested')
            ->andWhere('f.ownerKey = :owner')
            ->setParameter('owner', $ownerKey)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['articleId']] = (bool) $row['interested'];
        }

        return $map;
    }

    public function countInterested(int $articleId): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.article = :article')
            ->setParameter('article', $articleId)
            ->andWhere('f.interested = :interested')
            ->setParameter('interested', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

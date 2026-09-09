<?php

namespace App\Repository;

use App\Entity\Source;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Source> */
class SourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Source::class);
    }

    /** @return Source[] */
    public function findWithWebsite(): array
    {
        return $this->createQueryBuilder('s')->where('s.websiteUrl IS NOT NULL')
            ->andWhere("s.websiteUrl != ''")->orderBy('s.name', 'ASC')->getQuery()->getResult();
    }
}

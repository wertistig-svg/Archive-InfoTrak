<?php

namespace App\Repository;

use App\Entity\Source;
use App\InfoTrak\Catalog;
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
        return $this->createQueryBuilder('s')->distinct()->innerJoin('s.articles', 'a')
            ->where('s.websiteUrl IS NOT NULL')->andWhere("s.websiteUrl != ''")
            ->andWhere('a.isDemo = false')->andWhere('a.place IN (:coveredZones)')
            ->setParameter('coveredZones', Catalog::ZONES)
            ->orderBy('s.name', 'ASC')->getQuery()->getResult();
    }
}

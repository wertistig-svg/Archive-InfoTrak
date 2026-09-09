<?php

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Article> */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /** Filtres et exclusions appliqués avant la pagination. */
    public function feedPage(array $topics, array $zones, array $excludedIds, string $query, int $page, bool $includeDemo = false): array
    {
        $qb = $this->createQueryBuilder('a')->join('a.source', 's');
        if ($topics) { $qb->andWhere('a.category IN (:topics)')->setParameter('topics', $topics); }
        if ($zones) { $qb->andWhere('a.place IN (:zones)')->setParameter('zones', $zones); }
        if ($excludedIds) { $qb->andWhere('a.id NOT IN (:excluded)')->setParameter('excluded', $excludedIds); }
        if (!$includeDemo) { $qb->andWhere('a.isDemo = false'); }
        if ('' !== $query) {
            $needle = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($query)).'%';
            $qb->andWhere("LOWER(a.title) LIKE :query ESCAPE '!' OR LOWER(a.excerpt) LIKE :query ESCAPE '!' OR LOWER(s.name) LIKE :query ESCAPE '!'")
                ->setParameter('query', $needle);
        }
        $total = (int) (clone $qb)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / 12));
        $page = max(1, min($page, $pages));
        $items = $qb->addSelect('s')->orderBy('a.publishedAt', 'DESC')->addOrderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * 12)->setMaxResults(12)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return Article[] */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('s')
            ->join('a.source', 's')
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return Article[] */
    public function findForTopicsAndZones(array $topics, array $zones, int $limit = 20, bool $importantOnly = false): array
    {
        $qb = $this->createQueryBuilder('a')
            ->addSelect('s')
            ->join('a.source', 's')
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit);

        if ([] !== $topics) {
            $qb->andWhere('a.category IN (:topics)')->setParameter('topics', $topics);
        }
        if ([] !== $zones) {
            $qb->andWhere('a.place IN (:zones)')->setParameter('zones', $zones);
        }
        if ($importantOnly) {
            $qb->andWhere('a.isImportant = :imp')->setParameter('imp', true);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Article[] */
    public function findRelated(string $category, int $excludeId, int $limit = 3): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('s')
            ->join('a.source', 's')
            ->andWhere('a.category = :category')
            ->andWhere('a.isDemo = false')
            ->setParameter('category', $category)
            ->andWhere('a.id != :id')
            ->setParameter('id', $excludeId)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countVerified(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.isVerified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<string, int> libellé => nombre */
    public function countByCategory(bool $includeDemo = true): array
    {
        return $this->countByField('category', $includeDemo);
    }

    /** @return array<string, int> libellé => nombre */
    public function countByPlace(bool $includeDemo = true): array
    {
        return $this->countByField('place', $includeDemo);
    }

    /** @return array<string, int> */
    private function countByField(string $field, bool $includeDemo): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(sprintf('a.%s AS label, COUNT(a.id) AS total', $field))
            ->groupBy(sprintf('a.%s', $field))
            ->orderBy('total', 'DESC');
        if (!$includeDemo) { $qb->andWhere('a.isDemo = false'); }
        $rows = $qb->getQuery()->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['label']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return Article[] La limite borne le coût de l'analyse ; la page indique l'échantillon. */
    public function socialWindow(\DateTimeImmutable $since, \DateTimeImmutable $until, array $excludedIds = []): array
    {
        $qb = $this->createQueryBuilder('a')->addSelect('s')->join('a.source', 's')
            ->andWhere('a.isDemo = false')->andWhere('s.type = :type')->setParameter('type', 'social')
            ->andWhere('a.publishedAt >= :since AND a.publishedAt <= :until')->setParameter('since', $since)->setParameter('until', $until)
            ->orderBy('a.publishedAt', 'DESC')->addOrderBy('a.id', 'DESC')->setMaxResults(1000);
        if ($excludedIds) { $qb->andWhere('a.id NOT IN (:excluded)')->setParameter('excluded', $excludedIds); }
        return $qb->getQuery()->getResult();
    }

    public function collectionStats(\DateTimeImmutable $since, \DateTimeImmutable $until): array
    {
        $base = $this->createQueryBuilder('a')->where('a.isDemo = false');
        $sources = (int) (clone $base)->select('COUNT(DISTINCT a.source)')->getQuery()->getSingleScalarResult();
        $recent = (int) (clone $base)->select('COUNT(a.id)')->andWhere('a.publishedAt BETWEEN :since AND :until')
            ->setParameter('since', $since)->setParameter('until', $until)->getQuery()->getSingleScalarResult();
        return ['sources' => $sources, 'recent' => $recent];
    }
}

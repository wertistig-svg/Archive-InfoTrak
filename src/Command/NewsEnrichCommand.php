<?php

namespace App\Command;

use App\Entity\Article;
use App\News\ArticleEnrichment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:news:enrich', description: 'Complète les résumés récents depuis les pages publiques des éditeurs.')]
final class NewsEnrichCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private ArticleEnrichment $enrichment) { parent::__construct(); }
    protected function configure(): void { $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Articles à examiner', 60); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $db = $this->em->getConnection();
        if (!$db->fetchOne('SELECT pg_try_advisory_lock(974202611)')) { return Command::SUCCESS; }
        $count = 0;
        try {
            $articles = $this->em->getRepository(Article::class)->createQueryBuilder('a')
                ->join('a.source', 's')->where('a.isDemo = false')->andWhere('s.type = :type')->setParameter('type', 'press')
                ->andWhere('a.place IN (:zones)')->setParameter('zones', ['La Réunion', 'France'])
                ->orderBy('a.publishedAt', 'DESC')->setMaxResults(max(1, min(150, (int) $input->getOption('limit'))))->getQuery()->getResult();
            foreach ($articles as $article) {
                if ($this->enrichment->enrich($article)) { ++$count; $this->em->flush(); }
            }
        } finally { $db->fetchOne('SELECT pg_advisory_unlock(974202611)'); }
        $output->writeln($count.' résumé(s) complété(s).');
        return Command::SUCCESS;
    }
}

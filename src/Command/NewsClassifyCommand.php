<?php

namespace App\Command;

use App\News\FeedClassifier;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:news:classify', description: 'Reclasse les articles existants dans les sujets actuels ; simulation par défaut.')]
final class NewsClassifyCommand extends Command
{
    public function __construct(private readonly ArticleRepository $articles, private readonly EntityManagerInterface $em, #[Autowire('%kernel.project_dir%')] private readonly string $projectDir) { parent::__construct(); }
    protected function configure(): void { $this->addOption('apply', null, InputOption::VALUE_NONE, 'Appliquer après sauvegarde des anciennes catégories dans var/.'); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $changes = $rows = [];
        foreach ($this->articles->findBy(['isDemo' => false]) as $article) {
            $next = FeedClassifier::categoryFor($article->getTitle(), $article->getCategory());
            if ($next === $article->getCategory()) { continue; }
            $changes[] = ['id' => $article->getId(), 'before' => $article->getCategory(), 'after' => $next];
            $rows[] = [$article->getId(), $article->getCategory(), $next];
            if ($input->getOption('apply')) { $article->setCategory($next); }
        }
        $io->table(['Article', 'Avant', 'Après'], $rows);
        if ($input->getOption('apply') && $changes) {
            $path = $this->projectDir.'/var/categories-before-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.json';
            if (false === file_put_contents($path, json_encode($changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
                $io->error('Sauvegarde impossible : aucun changement enregistré.');
                return Command::FAILURE;
            }
            $this->em->flush();
            $io->success(count($changes).' articles reclassés. Sauvegarde : '.$path);
        } else { $io->note(count($changes).' changements proposés. Utiliser --apply pour les enregistrer.'); }
        return Command::SUCCESS;
    }
}

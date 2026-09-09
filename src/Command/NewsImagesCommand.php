<?php

namespace App\Command;

use App\Entity\Article;
use App\News\ImageService;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:news:images', description: 'Récupère les images d’aperçu (og:image) manquantes des articles.')]
class NewsImagesCommand extends Command
{
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly EntityManagerInterface $em,
        private readonly ImageService $images,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Articles max à traiter', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = max(1, (int) $input->getOption('limit'));

        $candidates = array_filter(
            $this->articles->findRecent($limit * 2),
            static fn (Article $a) => !$a->hasImage() && null !== $a->getSourceUrl(),
        );
        $candidates = \array_slice($candidates, 0, $limit);

        $found = 0;
        foreach ($candidates as $article) {
            $image = $this->images->extractImageUrl($article->getSourceUrl());
            if (null !== $image) {
                $article->setImageUrl($image);
                ++$found;
            }
        }
        $this->em->flush();

        $io->success(sprintf('%d image(s) récupérée(s) sur %d article(s) traité(s).', $found, \count($candidates)));

        return Command::SUCCESS;
    }
}

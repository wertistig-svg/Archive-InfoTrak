<?php

namespace App\Command;

use App\InfoTrak\VideoEmbed;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:article:set-video',
    description: 'Attache une vidéo lisible dans l’application à un article (mp4, YouTube, Vimeo).',
)]
class SetVideoCommand extends Command
{
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('slug', InputArgument::REQUIRED, 'Slug de l’article')
            ->addArgument('url', InputArgument::OPTIONAL, 'URL de la vidéo (mp4, YouTube, Vimeo)')
            ->addOption('remove', null, InputOption::VALUE_NONE, 'Retire la vidéo de l’article');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $article = $this->articles->findOneBy(['slug' => $input->getArgument('slug')]);
        if (null === $article) {
            $io->error('Article introuvable.');
            return Command::FAILURE;
        }

        if ($input->getOption('remove')) {
            $article->setVideoUrl(null);
            $this->em->flush();
            $io->success(sprintf('Vidéo retirée de « %s ».', $article->getTitle()));

            return Command::SUCCESS;
        }

        $url = (string) $input->getArgument('url');
        if ('' === $url || !VideoEmbed::supports($url)) {
            $io->error('URL non supportée : utilisez un mp4/webm/ogv direct, un lien YouTube (watch, youtu.be, shorts, embed) ou Vimeo.');

            return Command::FAILURE;
        }

        $article->setVideoUrl($url);
        $this->em->flush();
        $io->success(sprintf('Vidéo attachée à « %s ». Elle sera lue directement dans l’application.', $article->getTitle()));

        return Command::SUCCESS;
    }
}

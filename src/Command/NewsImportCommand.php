<?php

namespace App\Command;

use App\News\NewsImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:news:import', description: 'Importe des actualités depuis les flux RSS configurés (ré-exécutable, anti-doublons).')]
class NewsImportCommand extends Command
{
    public function __construct(
        private readonly NewsImportService $import,
        private readonly \Doctrine\DBAL\Connection $db,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('feed', null, InputOption::VALUE_REQUIRED, 'Slug du flux uniquement (voir NewsImportService::FEEDS)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Articles max par flux', 10)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans rien enregistrer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string|null $feed */
        $feed = $input->getOption('feed');
        $limit = max(1, (int) $input->getOption('limit'));

        if (!$this->db->fetchOne('SELECT pg_try_advisory_lock(974202610)')) {
            $io->note('Une collecte est déjà en cours.');
            return Command::SUCCESS;
        }
        try {
            $stats = $this->import->import($feed, $limit, (bool) $input->getOption('dry-run'));
        } catch (\Throwable $e) {
            $io->error('Import impossible : '.$e->getMessage());

            return Command::FAILURE;
        } finally {
            $this->db->fetchOne('SELECT pg_advisory_unlock(974202610)');
        }

        foreach ($stats['feeds'] as $feedName => $created) {
            $io->writeln(sprintf('  • %s : %d nouvel(s) article(s)', $feedName, $created));
        }
        foreach ($stats['errors'] as $feedName => $error) {
            $io->warning(sprintf('%s en panne (les autres flux continuent) : %s', $feedName, $error));
        }

        if ($input->getOption('dry-run')) {
            $io->note('Simulation : rien n’a été enregistré.');
            return Command::SUCCESS;
        }

        $io->success(sprintf('%d article(s) importé(s), %d ignoré(s). La boucle de collecte traite ensuite les abonnements aux notifications.', $stats['created'], $stats['skipped']));

        return Command::SUCCESS;
    }
}

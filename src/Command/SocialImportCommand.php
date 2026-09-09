<?php

namespace App\Command;

use App\InfoTrak\CommunityCatalog;
use App\Service\SocialImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:social:import', description: 'Collecte des messages publics via les connecteurs sociaux configurés.')]
final class SocialImportCommand extends Command
{
    public function __construct(private readonly SocialImportService $import) { parent::__construct(); }
    protected function configure(): void
    {
        $this->addArgument('network', InputArgument::REQUIRED, 'x, threads, instagram, facebook ou all')
            ->addOption('topic', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Sujet ou hashtag à rechercher')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum par réseau', 30)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Tester sans enregistrer');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $network = strtolower((string) $input->getArgument('network'));
        $networks = 'all' === $network ? SocialImportService::NETWORKS : [$network];
        $terms = $input->getOption('topic');
        if ([] === $terms) { $terms = array_map(static fn ($community) => $community['label'], CommunityCatalog::COMMUNITIES); }
        $failed = false;
        foreach ($networks as $item) {
            try {
                $result = $this->import->import($item, $terms, (int) $input->getOption('limit'), (bool) $input->getOption('dry-run'));
                $io->writeln(sprintf('• %s : %d ajouté(s), %d doublon(s)/incomplet(s)', $item, $result['created'], $result['skipped']));
            } catch (\Throwable $error) {
                $failed = true;
                $io->warning($item.' : '.$error->getMessage());
            }
        }
        if ($input->getOption('dry-run')) { $io->note('Simulation : aucune publication enregistrée.'); }
        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}

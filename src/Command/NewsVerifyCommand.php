<?php

namespace App\Command;

use App\News\VerificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:news:verify', description: 'Contrôle la correspondance avec la page source, sans certifier les faits.')]
class NewsVerifyCommand extends Command
{
    public function __construct(private readonly VerificationService $verification)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Articles récents max à contrôler', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $stats = $this->verification->verifyRecent(max(1, (int) $input->getOption('limit')));

        $io->success(sprintf(
            '%d article(s) contrôlé(s) : %d source(s) retrouvée(s), %d à recouper, %d source(s) inaccessible(s).',
            $stats['checked'],
            $stats['verified'],
            $stats['recheck'],
            $stats['unreachable'],
        ));

        return Command::SUCCESS;
    }
}

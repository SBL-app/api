<?php

namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:test-email-alert')]
class AppTestEmailAlertCommand extends Command
{
    public function __construct(private LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Test info — ne déclenchera pas d\'email');
        $this->logger->warning('Test warning — ne déclenchera pas d\'email');
        $this->logger->error('Test error — DOIT déclencher un email', [
            'context' => 'test manuel depuis app:test-email-alert',
        ]);

        $mailerTo = $_ENV['MAILER_TO'] ?? '(MAILER_TO non défini)';
        $output->writeln('<info>Log ERROR envoyé. Vérifier la boîte mail : ' . $mailerTo . '</info>');
        $output->writeln('<comment>Attends quelques secondes puis vérifie ta boîte mail.</comment>');
        $output->writeln('<comment>Pour tester l\'anti-spam, relance la commande dans les 5 prochaines minutes : aucun email ne devrait arriver.</comment>');

        return Command::SUCCESS;
    }
}

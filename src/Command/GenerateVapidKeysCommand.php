<?php

namespace App\Command;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-vapid-keys',
    description: 'Generate VAPID keys for Web Push notifications',
)]
class GenerateVapidKeysCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Generating VAPID keys for Web Push');

        $keys = VAPID::createVapidKeys();

        $io->success('VAPID keys generated successfully!');
        $io->section('Add these to your .env file:');
        $io->text([
            'VAPID_PUBLIC_KEY=' . $keys['publicKey'],
            'VAPID_PRIVATE_KEY=' . $keys['privateKey'],
            'VAPID_SUBJECT=mailto:admin@sbl.example.com',
        ]);

        $io->note('The public key must also be sent to the frontend for service worker registration.');

        return Command::SUCCESS;
    }
}

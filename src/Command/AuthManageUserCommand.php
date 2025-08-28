<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:auth:manage-user',
    description: 'Manage users and tokens for API authentication',
)]
class AuthManageUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform (create, token, list, activate, deactivate)')
            ->addArgument('username', InputArgument::OPTIONAL, 'Username for the action')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Password for user creation')
            ->addOption('roles', 'r', InputOption::VALUE_OPTIONAL, 'Roles for user creation (comma-separated)', 'ROLE_API')
            ->addOption('api-key', 'k', InputOption::VALUE_NONE, 'Generate API key for user')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force action without confirmation')
            ->setHelp(
                'This command allows you to manage users and tokens for API authentication.

Actions:
  create       Create a new user
  token        Generate a JWT token for an existing user
  list         List all users
  activate     Activate a user account
  deactivate   Deactivate a user account

Examples:
  # Create a user with API access
  php bin/console app:auth:manage-user create dev_user --password=secret --roles=ROLE_API --api-key

  # Generate a token for an existing user
  php bin/console app:auth:manage-user token dev_user

  # List all users
  php bin/console app:auth:manage-user list

  # Deactivate a user
  php bin/console app:auth:manage-user deactivate old_user
'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        return match ($action) {
            'create' => $this->createUser($input, $io),
            'token' => $this->generateToken($input, $io),
            'list' => $this->listUsers($io),
            'activate' => $this->toggleUserStatus($input, $io, true),
            'deactivate' => $this->toggleUserStatus($input, $io, false),
            default => $this->showHelp($io)
        };
    }

    private function createUser(InputInterface $input, SymfonyStyle $io): int
    {
        $username = $input->getArgument('username');
        if (!$username) {
            $username = $io->ask('Username');
        }

        // Vérifier si l'utilisateur existe déjà
        if ($this->userRepository->findOneBy(['username' => $username])) {
            $io->error("User '$username' already exists!");
            return Command::FAILURE;
        }

        $password = $input->getOption('password');
        if (!$password) {
            $password = $io->askHidden('Password');
        }

        $roles = explode(',', $input->getOption('roles'));
        $roles = array_map('trim', $roles);

        $user = new User();
        $user->setUsername($username);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles($roles);

        if ($input->getOption('api-key')) {
            $apiKey = bin2hex(random_bytes(32));
            $user->setApiKey($apiKey);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success("User '$username' created successfully!");

        if ($user->getApiKey()) {
            $io->section('API Key');
            $io->text($user->getApiKey());
        }

        return Command::SUCCESS;
    }

    private function generateToken(InputInterface $input, SymfonyStyle $io): int
    {
        $username = $input->getArgument('username');
        if (!$username) {
            $username = $io->ask('Username');
        }

        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user) {
            $io->error("User '$username' not found!");
            return Command::FAILURE;
        }

        if (!$user->isActive()) {
            $io->error("User '$username' is not active!");
            return Command::FAILURE;
        }

        $token = $this->jwtManager->create($user);

        $io->success("JWT Token generated for user '$username':");
        $io->text($token);

        return Command::SUCCESS;
    }

    private function listUsers(SymfonyStyle $io): int
    {
        $users = $this->userRepository->findAll();

        if (empty($users)) {
            $io->info('No users found.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($users as $user) {
            $rows[] = [
                $user->getId(),
                $user->getUsername(),
                implode(', ', $user->getRoles()),
                $user->isActive() ? 'Active' : 'Inactive',
                $user->getApiKey() ? 'Yes' : 'No',
                $user->getLastLogin()?->format('Y-m-d H:i:s') ?: 'Never'
            ];
        }

        $io->table(
            ['ID', 'Username', 'Roles', 'Status', 'Has API Key', 'Last Login'],
            $rows
        );

        return Command::SUCCESS;
    }

    private function toggleUserStatus(InputInterface $input, SymfonyStyle $io, bool $active): int
    {
        $username = $input->getArgument('username');
        if (!$username) {
            $username = $io->ask('Username');
        }

        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user) {
            $io->error("User '$username' not found!");
            return Command::FAILURE;
        }

        $action = $active ? 'activate' : 'deactivate';

        if (!$input->getOption('force')) {
            if (!$io->confirm("Are you sure you want to $action user '$username'?")) {
                $io->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        $user->setIsActive($active);
        $this->entityManager->flush();

        $status = $active ? 'activated' : 'deactivated';
        $io->success("User '$username' has been $status!");

        return Command::SUCCESS;
    }

    private function showHelp(SymfonyStyle $io): int
    {
        $io->error('Invalid action. Valid actions are: create, token, list, activate, deactivate');
        $io->text('Use --help to see more information.');
        return Command::FAILURE;
    }
}

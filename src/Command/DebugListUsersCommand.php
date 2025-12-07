<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug:list-users',
    description: 'List users for debugging purposes',
)]
class DebugListUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->entityManager->getRepository(User::class)->findAll();

        if (empty($users)) {
            $io->warning('No users found in the database.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($users as $user) {
            $rows[] = [
                $user->getEmail(),
                $user->getUserType(),
                $user->isVerified() ? 'Yes' : 'No',
                $user->isBlocked() ? 'Yes' : 'No',
                $user->getId()
            ];
        }

        $io->table(
            ['Email', 'Type', 'Verified', 'Blocked', 'ID'],
            $rows
        );

        return Command::SUCCESS;
    }
}

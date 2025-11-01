<?php

namespace App\Command;

use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:generate-dummy-messages', description: 'Generate dummy messages')]
class GenerateDummyMessagesCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Fetch users from the database
        $users = $this->entityManager->getRepository(User::class)->findAll();

        if (count($users) < 2) {
            $io->error('At least two users are required to generate messages.');
            return Command::FAILURE;
        }

        for ($i = 0; $i < 10; $i++) {
            $sender = $users[array_rand($users)];
            $receiver = $users[array_rand($users)];

            while ($sender === $receiver) {
                $receiver = $users[array_rand($users)];
            }

            $message = new Message();
            $message->setSender($sender);
            $message->setReceiver($receiver);
            $message->setText('Dummy message ' . ($i + 1));
            $message->setSeen((bool)random_int(0, 1));

            $this->entityManager->persist($message);
        }

        $this->entityManager->flush();

        $io->success('10 dummy messages have been created successfully.');
        return Command::SUCCESS;
    }
}

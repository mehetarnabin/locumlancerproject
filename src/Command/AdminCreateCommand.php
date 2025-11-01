<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:admin-create',
    description: 'Initial admin user create',
)]
class AdminCreateCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $admin = new User();
        $admin->setName('Superadmin');
        $admin->setEmail('admin@locumlancer.com');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin@99$'));
        $admin->setUserType(User::TYPE_ADMIN);
        $admin->setRoles(['ROLE_SUPER_ADMIN']);

        $this->em->persist($admin);
        $this->em->flush();

        $io->success('Successfully created admin account.');

        return Command::SUCCESS;
    }
}

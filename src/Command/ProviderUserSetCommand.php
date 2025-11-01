<?php

namespace App\Command;

use App\Entity\Provider;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:provider-user-set',
    description: 'Temp command to set user in provider',
)]
class ProviderUserSetCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->em->getRepository(User::class)->findBy(['userType' => User::TYPE_PROVIDER]);
        foreach ($users as $user){
            $provider = $user->getProvider();

            if($provider){
                $provider->setUser($user);
                $this->em->persist($provider);
            }
        }

        $this->em->flush();

        $io->success('Successfully updated user in provider.');

        return Command::SUCCESS;
    }
}

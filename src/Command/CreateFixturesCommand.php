<?php

namespace App\Command;

use App\Entity\Employer;
use App\Entity\Job;
use App\Entity\Provider;
use App\Entity\Profession;
use App\Entity\Speciality;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Faker\Factory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:create-fixtures',
    description: 'Creates 100 employers, 100 job seekers, and 1000 jobs.'
)]
class CreateFixturesCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $faker = Factory::create();

        // Get the user by email
        $userRepository = $this->em->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'testemployer@flexzob.com']);

        if (!$user) {
            throw new \Exception('User with email testemployer@flexzob.com not found.');
        }

        $employer = $user->getEmployer();

        // 3. Fetch Professions & Specialities
        $professions = $this->em->getRepository(Profession::class)->findAll();
        $specialities = $this->em->getRepository(Speciality::class)->findAll();

        if (empty($professions) || empty($specialities)) {
            $output->writeln('<error>Please seed Profession and Speciality entities first.</error>');
            return Command::FAILURE;
        }

        // 4. Create 100 Jobs
        for ($i = 0; $i < 100; $i++) {
            $job = new Job();
            $job->setTitle($faker->jobTitle());
            $job->setDescription("
                <p>{$faker->paragraph(3)}</p>
                <p>{$faker->paragraph(3)}</p>
                <p>{$faker->paragraph(3)}</p>
                <p>{$faker->paragraph(3)}</p>
                <p>{$faker->paragraph(3)}</p>
                <p>{$faker->paragraph(3)}</p>
                <ul>
                    <li>{$faker->sentence()}</li>
                    <li>{$faker->sentence()}</li>
                    <li>{$faker->sentence()}</li>
                </ul>
            ");
            $job->setHighlight("
                <ul>
                    <li>{$faker->sentence()}</li>
                    <li>{$faker->sentence()}</li>
                    <li>{$faker->sentence()}</li>
                </ul>
            ");
            $job->setStatus(Job::JOB_STATUS_PUBLISHED);
            $job->setUser($user); // as creator
            $job->setEmployer($employer);
            $job->setProfession($faker->randomElement($professions));
            $job->setSpeciality($faker->randomElement($specialities));
            $job->setCountry($faker->country);
            $job->setState($faker->state);
            $job->setCity($faker->city);
            $job->setStreetAddress($faker->streetAddress);
            $job->setExpirationDate($faker->dateTimeBetween('+1 month', '+6 months'));
            $job->setSchedule($faker->randomElement(['1pm-5pm', '12am-9am', '3am-5am']));
            $job->setStartDate($faker->dateTimeBetween('now', '+2 months'));
            $job->setYearOfExperience($faker->numberBetween(0, 10));
            $job->setPayRate($faker->randomElement(['Hourly', 'Daily']));
            $job->setPayRateHourly($faker->numberBetween(10, 80));
            $job->setPayRateDaily($faker->numberBetween(100, 800));
            $job->setWorkType($faker->randomElement(['Locums', 'Part time', 'Full time']));
            $job->setNeed($faker->randomElement(['Urgent', 'Routine', 'Long term']));
            $job->setBlocked(false);
            $job->setVerified(true);
            $job->setJobId(strtoupper(uniqid()));

            $this->em->persist($job);

            if ($i % 10 === 0) {
                $this->em->flush();
                $this->em->clear();

                // Re-fetch entities after clearing
                $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'testemployer@flexzob.com']);
                $employer = $user->getEmployer();
                $professions = $this->em->getRepository(Profession::class)->findAll();
                $specialities = $this->em->getRepository(Speciality::class)->findAll();

                $output->writeln("Inserted {$i} jobs...");
            }
        }

        $this->em->flush();
        $output->writeln('<info>✅ Successfully created 100 jobs.</info>');

        return Command::SUCCESS;
    }
}

<?php

use App\Kernel;
use App\Entity\ToDo;
use App\Entity\Recruiter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);

    return new class($kernel) extends \Symfony\Bundle\FrameworkBundle\Console\Application {
        public function doRun(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output)
        {
            $kernel = $this->getKernel();
            $kernel->boot();
            $container = $kernel->getContainer();
            /** @var EntityManagerInterface $em */
            $em = $container->get('doctrine')->getManager();

            $recruiter = $em->getRepository(Recruiter::class)->findOneBy([]);

            if (!$recruiter) {
                echo "No recruiter found.\n";
                return 0;
            }

            echo "Found Recruiter: " . $recruiter->getId() . "\n";

            $todo = new ToDo();
            $todo->setTitle('Verify Recruiter Dashboard To-Do');
            $todo->setDescription('This is a test task to ensure the To-Do list works for recruiters.');
            $todo->setRecruiter($recruiter);
            $todo->setProvider($em->getRepository(\App\Entity\Provider::class)->findOneBy([])); // Dummy provider if needed by constraints, though mapped nullable? Entity says nullable=false for provider?

            // Check provider constraint
            // #[ORM\JoinColumn(name: 'provider_id', nullable: false)]
            // private ?Provider $provider = null;
            // Ah, provider is NOT nullable in original entity. I need to set a provider!
            // Wait, this is bad design if ToDo is for Employer/Recruiter internal tasks.
            // But let's follow existing constraint.

            $provider = $em->getRepository(\App\Entity\Provider::class)->findOneBy([]);
            if ($provider) {
                $todo->setProvider($provider);
            }

            $em->persist($todo);
            $em->flush();

            echo "Created ToDo: " . $todo->getId() . "\n";

            return 0;
        }
    };
};

<?php

namespace App\Controller\Employer;

use App\Entity\Application;
use App\Entity\Job;
use App\Entity\Message;
use App\Entity\Notification;
use App\Repository\JobRepository;
use App\Service\ProfileAnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/employer')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_employer_dashboard')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        JobRepository $jobRepository,
    ): Response
    {
        $user = $this->getUser();

        $totalJobs = $em->createQuery("SELECT count(j.id) as total_jobs FROM App\Entity\Job j WHERE j.employer = :employer")
            ->setParameter('employer', $this->getUser()->getEmployer()->getId(), UuidType::NAME)
            ->getSingleScalarResult();

        $totalApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a JOIN a.job j WHERE j.employer = :employer")
            ->setParameter('employer', $this->getUser()->getEmployer()->getId(), UuidType::NAME)
            ->getSingleScalarResult();

        $totalInterviewedApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a JOIN a.job j WHERE j.employer = :employer AND a.status = :status")
            ->setParameter('employer', $this->getUser()->getEmployer()->getId(), UuidType::NAME)
            ->setParameter('status', 'interview')
            ->getSingleScalarResult();

        $totalHiredApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a JOIN a.job j WHERE j.employer = :employer AND a.status = :status")
            ->setParameter('employer', $this->getUser()->getEmployer()->getId(), UuidType::NAME)
            ->setParameter('status', 'hired')
            ->getSingleScalarResult();

        $statusCounts = $em->getRepository(Application::class)->getEmployerApplicationStatusCounts($this->getUser()->getEmployer()->getId());;

        $messages = $em->getRepository(Message::class)->findBy(['receiver' => $user], ['id' => 'DESC'], 10);
        $notifications = $em->getRepository(Notification::class)->findBy(['user' => $user], ['id' => 'DESC'], 5);

        $employer = $this->getUser()->getEmployer();
        $pastJobs = $jobRepository->getEmployerPastJobs($employer->getId());
        $currentJobs = $jobRepository->getEmployerCurrentJobs($employer->getId());

        return $this->render('employer/dashboard.html.twig', [
            'totalJobs' => $totalJobs,
            'totalApplications' => $totalApplications,
            'totalHiredApplications' => $totalHiredApplications,
            'totalInterviewedApplications' => $totalInterviewedApplications,
            'statusCounts'=> $statusCounts,
            'messages' => $messages,
            'notifications' => $notifications,
            'currentJobs' => $currentJobs,
            'pastJobs' => $pastJobs,
        ]);
    }

    // In DashboardController.php - temporary search tracking
    #[Route('/search/providers', name: 'app_employer_search_providers', methods: ['GET'])]
    public function searchProviders(
        Request $request,
        ProfileAnalyticsService $analyticsService,
        JobRepository $jobRepository
    ): Response {
        $searchQuery = $request->query->get('q', '');
        
        // Your search logic here - this is just an example
        $searchResults = []; // Replace with your actual search results
        
        // 🎯 COUNT BUTTON - Count each provider in search results
        foreach ($searchResults as $provider) {
            $analyticsService->recordSearchImpression($provider);
        }
        
        return $this->render('employer/search_providers.html.twig', [
            'providers' => $searchResults,
            'searchQuery' => $searchQuery,
        ]);
    }
}
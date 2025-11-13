<?php

namespace App\Controller\Provider;

use App\Entity\Application;
use App\Entity\Bookmark;
use App\Entity\Education;
use App\Entity\Experience;
use App\Entity\Job;
use App\Entity\License;
use App\Entity\Message;
use App\Entity\Notification;
use App\Service\OnboardingService;
use App\Service\ProfileAnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\BookmarkRepository;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/provider')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_provider_dashboard')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        OnboardingService $onboardingService,
        BookmarkRepository $bookmarkRepository,
        ProfileAnalyticsService $analyticsService
    ): Response
    {
        $user = $this->getUser();
        
        // Debug: Check if user exists and has provider
        if (!$user) {
            throw new \Exception('User not found');
        }
        
        $provider = $user->getProvider();
        
        // Debug: Check if provider exists
        if (!$provider) {
            // You might want to redirect to profile setup or show a message
            $this->addFlash('warning', 'Please complete your provider profile setup.');
        }

        $isOnboardingCompleted = $onboardingService->isProviderOnboardingCompleted($user);

        if(!$isOnboardingCompleted and !$provider->isSkipOnboarding()) {
            return $this->render('provider/onboard.html.twig', []);
        }

        // Get analytics data - this will handle null provider gracefully
        $analytics = $analyticsService->getProfileAnalytics($user);

        $bookmarks = $bookmarkRepository->findBy(['user' => $this->getUser()], ['id' => 'DESC']);
        $messages = $em->getRepository(Message::class)->findBy(['receiver' => $user], ['id' => 'DESC'], 10);
        $notifications = $em->getRepository(Notification::class)->findBy(['user' => $user], ['id' => 'DESC'], 5);

        // Only get matching jobs if provider exists and has necessary data
        $matchingJobs = null;
        if ($provider) {
            $filters['profession'] = $provider->getProfession()?->getId();
            $filters['specialities'] = $provider->getSpecialities();
            $filters['state'] = $provider->getDesiredStates() ? implode(',', $provider->getDesiredStates()) : null;
            $filters['limit'] = 5;

            $matchingJobs = $em->getRepository(Job::class)->getProviderMatchingJobs($filters);

            if(empty($filters['profession']) && empty($filters['speciality']) && empty($filters['state'])) {
                $matchingJobs = null;
            }
        }

        $applications = $em->getRepository(Application::class)->findBy(['provider' => $this->getUser()->getProvider()], ['id' => 'DESC'], 5);
        
        $statusCounts = [];
        if ($provider) {
            $statusCounts = $em->getRepository(Application::class)->getProviderApplicationStatusCounts($provider->getId());
        }
        $statusCounts[] = [
            'status' => 'saved',
            'count' => count($bookmarks),
        ];

        $totalApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a WHERE a.provider = :provider")
            ->setParameter('provider', $this->getUser()->getProvider()->getId(), UuidType::NAME)
            ->getSingleScalarResult();

        return $this->render('provider/dashboard.html.twig', [
            'bookmarks' => $bookmarks,
            'matchingJobs' => $matchingJobs,
            'statusCounts' => $statusCounts,
            'applications' => $applications,
            'messages' => $messages,
            'notifications' => $notifications,
            'totalApplications' => $totalApplications,
            'analytics' => $analytics,
            'hasProvider' => $provider !== null, // Pass this to template for conditional rendering
        ]);
    }

    #[Route('/skip-onboarding', name: 'app_provider_skip_onboarding')]
    public function skipOnboarding(
        EntityManagerInterface $em,
    ): Response
    {
        $user = $this->getUser();
        $provider = $user->getProvider();

        $provider->setSkipOnboarding(true);

        $em->persist($provider);
        $em->flush();

        return $this->redirectToRoute('app_provider_dashboard');
    }
}
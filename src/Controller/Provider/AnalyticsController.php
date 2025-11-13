<?php

namespace App\Controller\Provider;

use App\Entity\Application;
use App\Entity\Bookmark;
use App\Service\ProfileAnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/provider')]
class AnalyticsController extends AbstractController
{
    #[Route('/analytics', name: 'app_provider_analytics')]
    public function index(EntityManagerInterface $em, ProfileAnalyticsService $analyticsService): Response
    {
        $user = $this->getUser();
        
        // Get comprehensive analytics using the service
        $analytics = $analyticsService->getProfileAnalytics($user);
        
        // Get additional data for the full analytics page
        $provider = $user->getProvider();
        $bookmarks = $em->getRepository(Bookmark::class)->findBy(['user' => $user]);
        $totalBookmarks = count($bookmarks);
        
        // Monthly application trends (last 6 months)
        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = new \DateTime();
            $date->modify("-{$i} months");
            $monthKey = $date->format('Y-m');
            $monthName = $date->format('M Y');
            
            $monthStart = new \DateTime($monthKey . '-01');
            $monthEnd = clone $monthStart;
            $monthEnd->modify('last day of this month');
            
            $monthApplications = $em->getRepository(Application::class)
                ->createQueryBuilder('a')
                ->where('a.provider = :provider')
                ->andWhere('a.createdAt >= :start')
                ->andWhere('a.createdAt <= :end')
                ->setParameter('provider', $provider)
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd)
                ->getQuery()
                ->getResult();
            
            $monthlyData[] = [
                'month' => $monthName,
                'count' => count($monthApplications),
            ];
        }
        
        // Mock data for demonstration
        $impressions = 218;
        $profileViews = 156;
        $resumeDownloads = 42;
        
        return $this->render('provider/analytics.html.twig', [
            'analytics' => $analytics,
            'totalBookmarks' => $totalBookmarks,
            'monthlyData' => $monthlyData,
            'impressions' => $impressions,
            'profileViews' => $profileViews,
            'resumeDownloads' => $resumeDownloads,
        ]);
    }
}
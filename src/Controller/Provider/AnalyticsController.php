<?php

namespace App\Controller\Provider;

use App\Entity\Application;
use App\Entity\Bookmark;
use App\Service\ProfileAnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/provider')]
class AnalyticsController extends AbstractController
{

    #[Route('/analytics', name: 'app_provider_analytics')]
public function index(
    EntityManagerInterface $em, 
    ProfileAnalyticsService $analyticsService,
    Request $request
): Response
{
    $user = $this->getUser();
    $provider = $user->getProvider();

    if (!$provider) {
        return $this->redirectToRoute('app_provider_dashboard');
    }

     

    // Get timeframe from request or default to 6 months
    $timeframe = $request->query->get('trend_timeframe', 6);
    
    // Get analytics data from the service
    $analyticsData = $analyticsService->getProfileAnalytics($user);
    
    // Get application statistics
    $applications = $em->getRepository(Application::class)->findBy(['provider' => $provider]);
    $bookmarks = $em->getRepository(Bookmark::class)->findBy(['user' => $user]);
    
    // Calculate statistics
    $totalApplications = count($applications);
    $totalBookmarks = count($bookmarks);
    
    // Status counts
    $statusCounts = [
        'applied' => 0,
        'interview' => 0,
        'negotiating' => 0,
        'accepted' => 0,
        'completed' => 0,
        'rejected' => 0,
    ];
    
    foreach ($applications as $application) {
        $status = strtolower($application->getStatus() ?? '');
        if (isset($statusCounts[$status])) {
            $statusCounts[$status]++;
        }
    }
    
    // Application to Interview Ratio
    $interviewCount = $statusCounts['interview'];
    $appliedCount = $statusCounts['applied'] > 0 ? $statusCounts['applied'] : 1;
    $ratio = ($interviewCount / $appliedCount) * 100;
    
    // 🆕 FIX: Get monthly trends for APPLIED status only
    $monthlyData = $this->getMonthlyAppliedTrends($provider, $timeframe, $em);
    
    // Use actual analytics data for metrics
    $metrics = $analyticsData['metrics'];
    $skills = $analyticsData['skills'];
    $resume = $analyticsData['resume'];
    
    return $this->render('provider/analytics.html.twig', [
        'totalApplications' => $totalApplications,
        'totalBookmarks' => $totalBookmarks,
        'statusCounts' => $statusCounts,
        'ratio' => round($ratio, 1),
        'monthlyData' => $monthlyData,
        'topSkills' => $skills,
        
        // Actual analytics metrics
        'impressions' => $metrics['impressions'],
        'profileViews' => $metrics['profile_views'],
        'resumeDownloads' => $metrics['resume_downloads'],
        'responseRate' => round($metrics['response_rate'], 1),
        'profileCompleteness' => $resume['profileCompleteness'],
        'trends' => $metrics['trends'],
        
        // Additional data for charts
        'skillsData' => $skills,
        'currentTimeframe' => $timeframe,
    ]);
}

private function getMonthlyAppliedTrends($provider, int $months = 6, EntityManagerInterface $em): array
{
    $monthlyData = [];
    
    // Get all applications to count manually (more reliable)
    $allApplications = $em->getRepository(Application::class)
        ->findBy(['provider' => $provider], ['createdAt' => 'DESC']);
    
    for ($i = $months - 1; $i >= 0; $i--) {
        $date = new \DateTime();
        $date->modify("-$i months");
        $monthKey = $date->format('Y-m');
        $monthName = $date->format('M Y');
        
        $monthStart = new \DateTime($monthKey . '-01 00:00:00');
        $monthEnd = clone $monthStart;
        $monthEnd->modify('last day of this month 23:59:59');
        
        // Count applications for this month manually
        $monthCount = 0;
        foreach ($allApplications as $app) {
            $appDate = $app->getCreatedAt();
            if ($appDate && $appDate >= $monthStart && $appDate <= $monthEnd) {
                $monthCount++;
            }
        }
        
        $monthlyData[] = [
            'month' => $monthName,
            'count' => $monthCount,
            'year_month' => $monthKey
        ];
    }
    
    return $monthlyData;
}


}
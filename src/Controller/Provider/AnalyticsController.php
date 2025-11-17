<?php

namespace App\Controller\Provider;

use App\Entity\Application;
use App\Entity\Bookmark;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/provider')]
class AnalyticsController extends AbstractController
{
    #[Route('/analytics', name: 'app_provider_analytics')]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $provider = $user->getProvider();

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
        
        // Top skills (mock data for now - can be enhanced with actual data)
        $topSkills = [
            ['skill' => 'Nursing', 'count' => 45, 'percentage' => 85],
            ['skill' => 'Patient Care', 'count' => 38, 'percentage' => 72],
            ['skill' => 'Medical Records', 'count' => 32, 'percentage' => 60],
            ['skill' => 'Emergency Response', 'count' => 28, 'percentage' => 53],
            ['skill' => 'Medication Administration', 'count' => 25, 'percentage' => 47],
        ];
        
        // Profile views/impressions (mock data)
        $impressions = 218;
        $profileViews = 156;
        $resumeDownloads = 42;
        
        // Response rate
        $responseRate = $totalApplications > 0 ? ($interviewCount / $totalApplications) * 100 : 0;
        
        return $this->render('provider/analytics.html.twig', [
            'totalApplications' => $totalApplications,
            'totalBookmarks' => $totalBookmarks,
            'statusCounts' => $statusCounts,
            'ratio' => round($ratio, 1),
            'monthlyData' => $monthlyData,
            'topSkills' => $topSkills,
            'impressions' => $impressions,
            'profileViews' => $profileViews,
            'resumeDownloads' => $resumeDownloads,
            'responseRate' => round($responseRate, 1),
        ]);
    }
}


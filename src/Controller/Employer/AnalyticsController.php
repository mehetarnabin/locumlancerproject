<?php

namespace App\Controller\Employer;

use App\Entity\Application;
use App\Entity\Job;
use App\Service\ProfileAnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/employer')]
class AnalyticsController extends AbstractController
{
    #[Route('/analytics', name: 'app_employer_analytics')]
    public function index(
        EntityManagerInterface $em, 
        ProfileAnalyticsService $analyticsService,
        Request $request
    ): Response
    {
        $user = $this->getUser();
        $employer = $user->getEmployer();

        if (!$employer) {
            return $this->redirectToRoute('app_employer_dashboard');
        }

        // Get timeframe from request or default to 6 months
        $timeframe = $request->query->get('trend_timeframe', 6);
        
        // Get all jobs for this employer - count all jobs regardless of status
        $totalJobs = (int) $em->getRepository(Job::class)->count([
            'employer' => $employer
        ]);
        
        // Get total applications using query (more efficient)
        $totalApplications = $em->createQuery("SELECT count(a.id) as total_applications FROM App\Entity\Application a WHERE a.employer = :employer AND a.archivedAt IS NULL")
            ->setParameter('employer', $employer->getId(), UuidType::NAME)
            ->getSingleScalarResult();
        
        // Get all applications for calculations
        $applications = $em->getRepository(Application::class)
            ->createQueryBuilder('a')
            ->where('a.employer = :employer')
            ->andWhere('a.archivedAt IS NULL')
            ->setParameter('employer', $employer->getId(), UuidType::NAME)
            ->getQuery()
            ->getResult();
        
        // Get status counts using repository method (more efficient)
        $statusCountsRaw = $em->getRepository(Application::class)->getEmployerApplicationStatusCounts($employer->getId());
        
        // Initialize status counts array
        $statusCounts = [
            'applied' => 0,
            'interview' => 0,
            'negotiating' => 0,
            'accepted' => 0,
            'completed' => 0,
            'rejected' => 0,
        ];
        
        // Populate status counts from repository result
        foreach ($statusCountsRaw as $row) {
            $status = strtolower($row['status'] ?? '');
            if (isset($statusCounts[$status])) {
                $statusCounts[$status] = (int) ($row['count'] ?? 0);
            }
        }
        
        // Application to Interview Ratio
        $interviewCount = $statusCounts['interview'];
        $appliedCount = $statusCounts['applied'] > 0 ? $statusCounts['applied'] : 1;
        $ratio = ($interviewCount / $appliedCount) * 100;
        
        // Get monthly job posting trends
        $monthlyData = $this->getMonthlyJobTrends($employer, $timeframe, $em);
        
        // Get employer analytics data
        $employerInsights = $analyticsService->getEmployerProfileInsights($employer, $user);
        $topSkills = $analyticsService->getEmployerTopSkillsInDemand($employer);
        
        // Calculate response rate (how quickly employer responds to applications)
        $responseRate = $this->calculateResponseRate($applications);
        
        // Calculate job views/impressions - count total unique job views
        // This could be enhanced with a JobView entity if tracking is implemented
        $impressions = $totalJobs > 0 ? (int) ($totalApplications * 1.5) : 0; // Estimate based on applications
        
        // Profile views = total applications received (each application is a profile view)
        $profileViews = $totalApplications;
        
        // Resume downloads - count if there's a download tracking system
        // For now, estimate based on interviews (employers typically download resumes before interviews)
        $resumeDownloads = $statusCounts['interview'] + $statusCounts['accepted'] + $statusCounts['completed'];
        
        return $this->render('employer/analytics.html.twig', [
            'totalJobs' => $totalJobs,
            'totalApplications' => $totalApplications,
            'statusCounts' => $statusCounts,
            'ratio' => round($ratio, 1),
            'monthlyData' => $monthlyData,
            'topSkills' => $topSkills,
            
            // Analytics metrics
            'impressions' => $impressions,
            'profileViews' => $profileViews,
            'resumeDownloads' => $resumeDownloads,
            'responseRate' => round($responseRate, 1),
            'profileCompleteness' => $employerInsights['profileCompleteness'],
            
            // Additional data for charts
            'skillsData' => $topSkills,
            'currentTimeframe' => $timeframe,
        ]);
    }

    private function getMonthlyJobTrends($employer, int $months = 6, EntityManagerInterface $em): array
    {
        $monthlyData = [];
        
        // Get all jobs to count manually
        $allJobs = $em->getRepository(Job::class)
            ->findBy(['employer' => $employer], ['createdAt' => 'DESC']);
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = new \DateTime();
            $date->modify("-$i months");
            $monthKey = $date->format('Y-m');
            $monthName = $date->format('M Y');
            
            $monthStart = new \DateTime($monthKey . '-01 00:00:00');
            $monthStart->setTime(0, 0, 0);
            
            $monthEnd = clone $monthStart;
            $monthEnd->modify('last day of this month');
            $monthEnd->setTime(23, 59, 59);
            
            // Count jobs posted in this month manually
            $monthCount = 0;
            foreach ($allJobs as $job) {
                $jobDate = $job->getCreatedAt();
                if ($jobDate) {
                    // Convert to DateTime if it's not already
                    if ($jobDate instanceof \DateTimeImmutable) {
                        $jobDate = \DateTime::createFromImmutable($jobDate);
                    }
                    if ($jobDate instanceof \DateTime) {
                        $jobDate->setTime(0, 0, 0);
                        if ($jobDate >= $monthStart && $jobDate <= $monthEnd) {
                            $monthCount++;
                        }
                    }
                }
            }
            
            $monthlyData[] = [
                'month' => $monthName,
                'count' => (int) $monthCount,
                'year_month' => $monthKey
            ];
        }
        return $monthlyData;
    }

    private function calculateResponseRate(array $applications): float
    {
        if (empty($applications)) {
            return 0.0;
        }

        $respondedApplications = 0;
        foreach ($applications as $application) {
            $status = strtolower($application->getStatus() ?? '');
            
            // Count as "responded" if employer has taken any meaningful action
            if (in_array($status, ['interview', 'negotiating', 'accepted', 'rejected', 'hired', 'reviewed', 'in_review'])) {
                $respondedApplications++;
            }
            // OR if there's an interview scheduled
            elseif ($application->getInterview() !== null) {
                $respondedApplications++;
            }
            // OR if contract was sent
            elseif ($application->getContractSentAt() !== null) {
                $respondedApplications++;
            }
        }

        return ($respondedApplications / count($applications)) * 100;
    }
}

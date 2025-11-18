<?php
// src/Service/ProfileAnalyticsService.php

namespace App\Service;

use App\Entity\Provider;
use App\Entity\User;
use App\Repository\ApplicationRepository;
use App\Repository\JobRepository;
use App\Repository\ProfileAnalyticsRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProfileAnalyticsService
{
    public function __construct(
        private ApplicationRepository $applicationRepo,
        private JobRepository $jobRepo,
        private ProfileAnalyticsRepository $profileAnalyticsRepo,
        private EntityManagerInterface $entityManager
    ) {}

    public function getProfileAnalytics(User $user): array
    {
        $provider = $user->getProvider();
        
        // Check if provider exists
        if (!$provider) {
            return $this->getEmptyAnalytics();
        }
        
        return [
            'ratio' => $this->getApplicationToInterviewRatio($provider),
            'skills' => $this->getTopSkillsInDemand($provider),
            'resume' => $this->getResumeInsights($provider, $user),
            'metrics' => $this->getProfileMetrics($provider),
        ];
    }

    public function getProfileMetrics(Provider $provider): array
    {
        try {
            // Get current month metrics
            $startOfMonth = new \DateTime('first day of this month 00:00:00');
            $currentMetrics = $this->profileAnalyticsRepo->getProviderMetrics($provider, $startOfMonth);
            
            // Get previous month metrics for comparison
            $startOfLastMonth = new \DateTime('first day of last month 00:00:00');
            $previousMetrics = $this->profileAnalyticsRepo->getProviderMetrics($provider, $startOfLastMonth);

            // Calculate response rate from applications
            $responseRate = $this->calculateResponseRate($provider);

            return [
                'impressions' => $currentMetrics['total_impressions'],
                'profile_views' => $currentMetrics['profile_views'],
                'resume_downloads' => $currentMetrics['resume_downloads'],
                'response_rate' => $responseRate,
                'trends' => $this->calculateTrends($currentMetrics, $previousMetrics),
            ];
        } catch (\Exception $e) {
            error_log('Error in getProfileMetrics: ' . $e->getMessage());
            return $this->getDefaultMetrics();
        }
    }

    private function calculateResponseRate(Provider $provider): float
{
    $applications = $this->applicationRepo->findBy(['provider' => $provider]);
    
    if (empty($applications)) {
        return 0.0;
    }

    $respondedApplications = 0;
    foreach ($applications as $application) {
        $status = strtolower($application->getStatus() ?? '');
        
        // Count as "responded" if employer has taken any meaningful action
        // This uses only existing status fields
        if (in_array($status, ['interview', 'negotiating', 'accepted', 'rejected', 'hired', 'reviewed'])) {
            $respondedApplications++;
        }
        // OR if there's an interview scheduled (using existing interview relation)
        elseif ($application->getInterview() !== null) {
            $respondedApplications++;
        }
        // OR if contract was sent (using existing contract fields)
        elseif ($application->getContractSentAt() !== null) {
            $respondedApplications++;
        }
    }

    return ($respondedApplications / count($applications)) * 100;
}
    private function calculateTrends(array $current, array $previous): array
    {
        $trends = [];
        
        foreach ($current as $key => $currentValue) {
            $previousValue = $previous[$key] ?? 0;
            
            if ($previousValue > 0) {
                $change = (($currentValue - $previousValue) / $previousValue) * 100;
            } else {
                $change = $currentValue > 0 ? 100 : 0;
            }
            
            $trends[$key] = round($change, 1);
        }
        
        return $trends;
    }

    private function getDefaultMetrics(): array
    {
        return [
            'impressions' => 0,
            'profile_views' => 0,
            'resume_downloads' => 0,
            'response_rate' => 0,
            'trends' => [
                'total_impressions' => 0,
                'profile_views' => 0,
                'resume_downloads' => 0,
            ],
        ];
    }

    // Record analytics events
    public function recordProfileView(Provider $provider, ?User $viewer = null): void
    {
        $this->profileAnalyticsRepo->recordAnalyticsEvent($provider, $viewer, 'profile_view');
    }

    public function recordResumeDownload(Provider $provider, ?User $downloader = null): void
    {
        $this->profileAnalyticsRepo->recordAnalyticsEvent($provider, $downloader, 'resume_download');
    }

    public function recordSearchImpression(Provider $provider): void
    {
        $this->profileAnalyticsRepo->recordAnalyticsEvent($provider, null, 'search_impression');
    }

    private function getApplicationToInterviewRatio(Provider $provider): array
    {
        try {
            // Get all applications for this provider
            $applications = $this->applicationRepo->findBy(['provider' => $provider]);
            
            $totalApplications = count($applications);
            
            // Count interviews and negotiating statuses
            $interviewCount = 0;
            foreach ($applications as $application) {
                $status = strtolower($application->getStatus() ?? '');
                if (in_array($status, ['interview', 'negotiating'])) {
                    $interviewCount++;
                }
            }
            
            $interviewRate = $totalApplications > 0 ? ($interviewCount / $totalApplications) * 100 : 0;

            return [
                'totalApplications' => $totalApplications,
                'totalInterviews' => $interviewCount,
                'interviewRate' => round($interviewRate, 1),
            ];
        } catch (\Exception $e) {
            return [
                'totalApplications' => 0,
                'totalInterviews' => 0,
                'interviewRate' => 0,
            ];
        }
    }

    private function getTopSkillsInDemand(Provider $provider): array
    {
        try {
            // Get provider's profession - this should exist
            $providerProfession = $provider->getProfession();
            
            if (!$providerProfession) {
                return $this->getDefaultSkills();
            }

            // Get jobs that match the provider's profile - use only available data
            $searchParams = [
                'profession' => $providerProfession->getId(),
                'limit' => 50
            ];

            // Add speciality if available (based on Job entity structure)
            if (method_exists($provider, 'getSpeciality') && $provider->getSpeciality()) {
                $searchParams['speciality'] = $provider->getSpeciality()->getId();
            }

            // Add states if available
            if (method_exists($provider, 'getDesiredStates') && $provider->getDesiredStates()) {
                $searchParams['state'] = implode(',', $provider->getDesiredStates());
            }

            $matchingJobs = $this->jobRepo->getProviderMatchingJobs($searchParams);

            if (empty($matchingJobs)) {
                return $this->getDefaultSkills();
            }

            // Extract and analyze skills from job descriptions
            $skillFrequency = $this->analyzeSkillsFromJobs($matchingJobs);
            
            // Get provider's current skills
            $providerSkills = $this->getProviderSkills($provider);
            
            // Calculate skill gaps and demand
            $skillsData = $this->calculateSkillDemand($skillFrequency, $providerSkills);
            
            return array_slice($skillsData, 0, 5);
            
        } catch (\Exception $e) {
            error_log('Error in getTopSkillsInDemand: ' . $e->getMessage());
            return $this->getDefaultSkills();
        }
    }

    private function analyzeSkillsFromJobs(array $jobs): array
    {
        $skillFrequency = [];
        $commonSkills = [
            'patient care', 'medical records', 'emergency response', 'medication administration',
            'treatment planning', 'clinical skills', 'healthcare', 'nursing', 'medical procedures',
            'patient assessment', 'vital signs', 'patient education', 'team collaboration',
            'communication skills', 'electronic health records', 'medical terminology'
        ];

        foreach ($jobs as $job) {
            // Analyze job title and description
            $text = strtolower($job->getTitle() . ' ' . $job->getDescription());
            
            foreach ($commonSkills as $skill) {
                if (strpos($text, $skill) !== false) {
                    $skillFrequency[$skill] = ($skillFrequency[$skill] ?? 0) + 1;
                }
            }
            
            // Check for specific skills from job requirements
            if (method_exists($job, 'getRequirements')) {
                $requirements = strtolower($job->getRequirements() ?? '');
                foreach ($commonSkills as $skill) {
                    if (strpos($requirements, $skill) !== false) {
                        $skillFrequency[$skill] = ($skillFrequency[$skill] ?? 0) + 2;
                    }
                }
            }
        }

        return $skillFrequency;
    }

    private function getProviderSkills(Provider $provider): array
    {
        $skills = [];
        
        // Add profession
        if ($provider->getProfession()) {
            $skills[] = strtolower($provider->getProfession()->getName());
        }
        
        // Add speciality (singular - based on Job entity)
        if (method_exists($provider, 'getSpeciality') && $provider->getSpeciality()) {
            $speciality = $provider->getSpeciality();
            if (method_exists($speciality, 'getName')) {
                $skills[] = strtolower($speciality->getName());
            }
        }
        
        // Add skills from user bio (from User entity, not Provider)
        $user = $provider->getUser();
        if ($user && $user->getBio()) {
            $bio = strtolower($user->getBio());
            $commonSkills = ['patient care', 'medical records', 'emergency response', 'medication administration'];
            foreach ($commonSkills as $skill) {
                if (strpos($bio, $skill) !== false && !in_array($skill, $skills)) {
                    $skills[] = $skill;
                }
            }
        }
        
        return array_unique($skills);
    }

    private function calculateSkillDemand(array $skillFrequency, array $providerSkills): array
    {
        $totalJobs = array_sum($skillFrequency);
        $skillsData = [];
        
        $colors = ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899', '#84cc16'];
        $colorIndex = 0;
        
        foreach ($skillFrequency as $skill => $count) {
            if ($totalJobs > 0) {
                $percentage = ($count / $totalJobs) * 100;
                $percentage = min(95, max(5, $percentage));
                
                $formattedSkill = ucwords($skill);
                $hasSkill = in_array($skill, $providerSkills);
                
                $skillsData[] = [
                    'name' => $formattedSkill,
                    'percentage' => round($percentage),
                    'color' => $colors[$colorIndex % count($colors)],
                    'hasSkill' => $hasSkill,
                    'demandLevel' => $this->getDemandLevel($percentage),
                    'suggestion' => !$hasSkill ? "Consider adding " . $formattedSkill . " to your profile" : null
                ];
                
                $colorIndex++;
            }
        }
        
        // Sort by percentage descending
        usort($skillsData, fn($a, $b) => $b['percentage'] <=> $a['percentage']);
        
        return $skillsData;
    }

    private function getDemandLevel(float $percentage): string
    {
        if ($percentage >= 70) return 'high';
        if ($percentage >= 40) return 'medium';
        return 'low';
    }

    private function getDefaultSkills(): array
    {
        return [
            [
                'name' => 'Patient Care',
                'percentage' => 85,
                'color' => '#10b981',
                'hasSkill' => true,
                'demandLevel' => 'high'
            ],
            [
                'name' => 'Medical Records',
                'percentage' => 65,
                'color' => '#3b82f6',
                'hasSkill' => false,
                'demandLevel' => 'medium'
            ],
            [
                'name' => 'Emergency Response',
                'percentage' => 45,
                'color' => '#f59e0b',
                'hasSkill' => false,
                'demandLevel' => 'medium'
            ]
        ];
    }

    private function getResumeInsights(Provider $provider, User $user): array
    {
        try {
            $completenessScore = 0;
            $missingFields = [];
            
            // Check profile completeness with only available methods
            $fieldsToCheck = [
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'phone1' => $user->getPhone1(),
                'bio' => $user->getBio(),
                'profession' => $provider->getProfession(),
            ];

            // Add optional fields if they exist
            if (method_exists($provider, 'getSpeciality')) {
                $fieldsToCheck['speciality'] = (bool) $provider->getSpeciality();
            }

            if (method_exists($provider, 'getDesiredStates')) {
                $fieldsToCheck['desiredStates'] = $provider->getDesiredStates() && !empty($provider->getDesiredStates());
            }

            if (method_exists($provider, 'getLocation')) {
                $fieldsToCheck['location'] = $provider->getLocation();
            }
            
            $completedFields = 0;
            foreach ($fieldsToCheck as $field => $value) {
                if (in_array($field, ['speciality', 'desiredStates'])) {
                    // Boolean fields
                    if ($value === true) {
                        $completedFields++;
                    } else {
                        $missingFields[] = $this->formatFieldName($field);
                    }
                } elseif (!empty($value)) {
                    $completedFields++;
                } else {
                    $missingFields[] = $this->formatFieldName($field);
                }
            }
            
            $completenessScore = count($fieldsToCheck) > 0 ? ($completedFields / count($fieldsToCheck)) * 100 : 0;
            
            return [
                'profileCompleteness' => round($completenessScore),
                'missingFields' => $missingFields,
                'suggestions' => $this->getProfileSuggestions($missingFields),
            ];
        } catch (\Exception $e) {
            return [
                'profileCompleteness' => 0,
                'missingFields' => ['Profile information'],
                'suggestions' => ['Complete your profile to get better job matches'],
            ];
        }
    }

    private function getEmptyAnalytics(): array
    {
        return [
            'ratio' => [
                'totalApplications' => 0,
                'totalInterviews' => 0,
                'interviewRate' => 0,
            ],
            'skills' => [],
            'resume' => [
                'profileCompleteness' => 0,
                'missingFields' => ['Complete your profile setup'],
                'suggestions' => ['Set up your provider profile to start using analytics'],
            ],
            'metrics' => $this->getDefaultMetrics(),
        ];
    }

    private function formatFieldName(string $field): string
    {
        $fieldNames = [
            'name' => 'Full Name',
            'phone1' => 'Phone Number',
            'desiredStates' => 'Preferred Locations',
            'speciality' => 'Specialty',
        ];
        
        return $fieldNames[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    private function getProfileSuggestions(array $missingFields): array
    {
        $suggestions = [];
        
        if (in_array('Profession', $missingFields)) {
            $suggestions[] = 'Add your profession to get better job matches';
        }
        
        if (in_array('Specialty', $missingFields)) {
            $suggestions[] = 'Add your specialty to attract relevant employers';
        }
        
        if (in_array('Preferred Locations', $missingFields)) {
            $suggestions[] = 'Set your preferred work locations for better job matching';
        }
        
        if (in_array('Full Name', $missingFields)) {
            $suggestions[] = 'Add your full name to personalize your profile';
        }
        
        if (in_array('Phone Number', $missingFields)) {
            $suggestions[] = 'Add your phone number so employers can contact you';
        }
        
        if (in_array('Bio', $missingFields)) {
            $suggestions[] = 'Write a compelling bio to stand out to employers';
        }
        
        return array_slice($suggestions, 0, 3);
    }

    // Add this method to your ProfileAnalyticsService.php
// In ProfileAnalyticsService.php - Update the method
public function getMonthlyApplicationTrends(Provider $provider, int $months = 6): array
{
    try {
        $monthlyData = [];
        $endDate = new \DateTime();
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = new \DateTime();
            $date->modify("-$i months");
            $monthKey = $date->format('Y-m');
            $monthName = $date->format('M Y');
            
            $monthStart = new \DateTime($monthKey . '-01 00:00:00');
            $monthEnd = clone $monthStart;
            $monthEnd->modify('last day of this month 23:59:59');
            
            // 🆕 COUNT ONLY "APPLIED" STATUS APPLICATIONS FOR THIS MONTH
            $monthApplications = $this->applicationRepo->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.provider = :provider')
                ->andWhere('a.status = :status') // 🆕 ONLY COUNT APPLIED STATUS
                ->andWhere('a.createdAt >= :start')
                ->andWhere('a.createdAt <= :end')
                ->setParameter('provider', $provider)
                ->setParameter('status', 'applied') // 🆕 ONLY APPLIED STATUS
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd)
                ->getQuery()
                ->getSingleScalarResult();
            
            $monthlyData[] = [
                'month' => $monthName,
                'count' => (int)$monthApplications,
                'year_month' => $monthKey
            ];
        }
        
        return $monthlyData;
        
    } catch (\Exception $e) {
        error_log('Error in getMonthlyApplicationTrends: ' . $e->getMessage());
        return $this->getDefaultMonthlyTrends($months);
    }
}

    private function getDefaultMonthlyTrends(int $months = 6): array
    {
        $monthlyData = [];
        $currentDate = new \DateTime();
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = new \DateTime();
            $date->modify("-$i months");
            $monthName = $date->format('M Y');
            
            $monthlyData[] = [
                'month' => $monthName,
                'count' => 0,
                'year_month' => $date->format('Y-m')
            ];
        }
        
        return $monthlyData;
    }

    private function calculateAverageResponseTime(Provider $provider): float
{
    try {
        $applications = $this->applicationRepo->findBy(['provider' => $provider]);
        
        if (empty($applications)) {
            return 0.0;
        }

        $totalResponseTime = 0;
        $respondedApplications = 0;

        foreach ($applications as $application) {
            $appliedDate = $application->getCreatedAt();
            $statusUpdatedAt = $application->getUpdatedAt(); // When status was changed
            
            if ($statusUpdatedAt && $appliedDate) {
                $responseTime = $statusUpdatedAt->getTimestamp() - $appliedDate->getTimestamp();
                $responseTimeHours = $responseTime / 3600; // Convert to hours
                
                if ($responseTimeHours > 0 && $responseTimeHours < 720) { // Reasonable range: 0-30 days
                    $totalResponseTime += $responseTimeHours;
                    $respondedApplications++;
                }
            }
        }

        return $respondedApplications > 0 ? round($totalResponseTime / $respondedApplications, 1) : 0.0;
    } catch (\Exception $e) {
        return 0.0;
    }
}
}
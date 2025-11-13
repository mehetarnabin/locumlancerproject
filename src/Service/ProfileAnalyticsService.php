<?php
// src/Service/ProfileAnalyticsService.php

namespace App\Service;

use App\Entity\User;
use App\Repository\ApplicationRepository;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProfileAnalyticsService
{
    public function __construct(
        private ApplicationRepository $applicationRepo,
        private JobRepository $jobRepo,
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
        ];
    }

    private function getApplicationToInterviewRatio($provider): array
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

    private function getTopSkillsInDemand($provider): array
    {
        try {
            // Get provider's skills from profile
            $providerSkills = [];
            
            // Check if provider has skills method
            if (method_exists($provider, 'getSkills') && $provider->getSkills()) {
                $providerSkills = $provider->getSkills();
            }
            
            // Check specialities
            if (method_exists($provider, 'getSpecialities') && $provider->getSpecialities()) {
                foreach ($provider->getSpecialities() as $speciality) {
                    if (method_exists($speciality, 'getName')) {
                        $providerSkills[] = $speciality->getName();
                    }
                }
            }
            
            // Check profession
            if (method_exists($provider, 'getProfession') && $provider->getProfession()) {
                $profession = $provider->getProfession();
                if (method_exists($profession, 'getName')) {
                    $providerSkills[] = $profession->getName();
                }
            }

            // Remove duplicates and empty values
            $providerSkills = array_unique(array_filter($providerSkills));

            // Sample data - in real implementation, you'd query jobs matching these skills
            $skillsData = [];
            $sampleSkills = [
                'Nursing', 'Patient Care', 'Medical Records', 'Emergency Response', 
                'Medication Administration', 'Healthcare', 'Clinical Skills',
                'Patient Assessment', 'Treatment Planning', 'Medical Procedures'
            ];
            
            $sampleColors = ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#ec4899', '#84cc16', '#14b8a6'];
            
            foreach ($sampleSkills as $index => $skill) {
                // Check if provider has this skill or if we should show it as a suggestion
                if (in_array($skill, $providerSkills) || count($skillsData) < 5) {
                    $percentage = in_array($skill, $providerSkills) ? rand(70, 95) : rand(40, 65);
                    $skillsData[] = [
                        'name' => $skill,
                        'percentage' => $percentage,
                        'color' => $sampleColors[$index] ?? '#10b981',
                        'hasSkill' => in_array($skill, $providerSkills),
                    ];
                }
            }
            
            // Sort by percentage descending
            usort($skillsData, fn($a, $b) => $b['percentage'] <=> $a['percentage']);
            
            return array_slice($skillsData, 0, 5);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getResumeInsights($provider, User $user): array
    {
        try {
            $completenessScore = 0;
            $missingFields = [];
            
            // Check profile completeness - use the actual methods from your User entity
            $fieldsToCheck = [
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'phone1' => $user->getPhone1(),
                'bio' => $user->getBio(),
                'profession' => method_exists($provider, 'getProfession') ? $provider->getProfession() : null,
                'specialities' => method_exists($provider, 'getSpecialities') ? ($provider->getSpecialities() && !$provider->getSpecialities()->isEmpty()) : false,
                'experience' => method_exists($provider, 'getExperience') ? $provider->getExperience() : null,
                'education' => method_exists($provider, 'getEducation') ? $provider->getEducation() : null,
                'desiredStates' => method_exists($provider, 'getDesiredStates') ? ($provider->getDesiredStates() && !empty($provider->getDesiredStates())) : false,
                'location' => method_exists($provider, 'getLocation') ? $provider->getLocation() : null,
            ];
            
            $completedFields = 0;
            foreach ($fieldsToCheck as $field => $value) {
                // For boolean fields (like specialities, desiredStates), check if they're true
                if ($field === 'specialities' || $field === 'desiredStates') {
                    if ($value === true) {
                        $completedFields++;
                    } else {
                        $missingFields[] = $this->formatFieldName($field);
                    }
                } 
                // For other fields, check if they're not empty
                elseif (!empty($value)) {
                    $completedFields++;
                } else {
                    $missingFields[] = $this->formatFieldName($field);
                }
            }
            
            $completenessScore = ($completedFields / count($fieldsToCheck)) * 100;
            
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
        ];
    }

    private function formatFieldName(string $field): string
    {
        $fieldNames = [
            'name' => 'Full Name',
            'phone1' => 'Phone Number',
            'desiredStates' => 'Preferred Locations',
            'specialities' => 'Specialties',
        ];
        
        return $fieldNames[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    private function getProfileSuggestions(array $missingFields): array
    {
        $suggestions = [];
        
        if (in_array('Profession', $missingFields)) {
            $suggestions[] = 'Add your profession to get better job matches';
        }
        
        if (in_array('Specialties', $missingFields)) {
            $suggestions[] = 'Add your specialties to attract relevant employers';
        }
        
        if (in_array('Experience', $missingFields)) {
            $suggestions[] = 'Include your work experience to showcase your expertise';
        }
        
        if (in_array('Education', $missingFields)) {
            $suggestions[] = 'Add your education background to build credibility';
        }
        
        if (in_array('Bio', $missingFields)) {
            $suggestions[] = 'Write a compelling bio to stand out to employers';
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
        
        return array_slice($suggestions, 0, 3);
    }
}
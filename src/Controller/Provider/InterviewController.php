<?php

namespace App\Controller\Provider;

use App\Repository\InterviewRepository;
use App\Repository\ApplicationRepository;
use App\Repository\BookmarkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Types\UuidType;

#[Route('/provider')]
class InterviewController extends AbstractController
{
    #[Route('/interviews/calendar', name: 'app_provider_interview_calendar')]
    public function calendarView(Request $request, EntityManagerInterface $em)
    {
        $jobId = $request->query->get('jobId');
        $applicationId = $request->query->get('applicationId');
        $status = $request->query->get('status'); // 'saved' or application status
        
        return $this->render('provider/application/calendar.html.twig', [
            'jobId' => $jobId,
            'applicationId' => $applicationId,
            'status' => $status,
        ]);
    }

    #[Route('/interviews/calendar-data', name: 'app_provider_interview_calendar_data')]
    public function calendarData(
        InterviewRepository $interviewRepository,
        ApplicationRepository $applicationRepository,
        BookmarkRepository $bookmarkRepository,
        Request $request,
        EntityManagerInterface $em
    )
    {
        try {
            $provider = $this->getUser()->getProvider();
            if (!$provider) {
                return $this->json(['error' => 'Provider not found'], 403);
            }

            $jobId = $request->query->get('jobId');
            $applicationId = $request->query->get('applicationId');
            $status = $request->query->get('status');
            
            $events = [];
            $selectedJobId = null;
            
            // Get jobId from applicationId if provided
            if ($applicationId) {
                $application = $applicationRepository->find($applicationId);
                if ($application && $application->getProvider() === $provider && $application->getJob()) {
                    $selectedJobId = $application->getJob()->getId()->toString();
                }
            } elseif ($jobId) {
                $selectedJobId = (string)$jobId;
            }

            // Always get all applications for the provider to show all interview dates
            $applications = $applicationRepository->findBy([
                'provider' => $provider
            ], ['createdAt' => 'DESC']);

            foreach ($applications as $application) {
                $isSelectedJob = $selectedJobId && $application->getJob() && 
                                $application->getJob()->getId()->toString() === $selectedJobId;
                $events = array_merge($events, $this->getApplicationTimelineEvents($application, $isSelectedJob));
            }

            // Also include interview events - show all interviews but highlight selected job's interviews
            $interviews = $interviewRepository->getProviderInterviews($provider->getId());
            foreach ($interviews as $interview) {
                $application = $interview->getApplication();
                if (!$application) continue;
                
                // Skip if interview date is missing
                $interviewDate = $interview->getDate();
                if (!$interviewDate) continue;
                
                $employerName = $application->getEmployer()?->getName();
                $jobTitle = $application->getJob()?->getTitle();
                $interviewJobId = $application->getJob() ? $application->getJob()->getId()->toString() : null;
                $isSelectedJob = $selectedJobId && $interviewJobId === $selectedJobId;

                // Color coding by platform
                $platform = strtolower($interview->getMeetingPlatform() ?? '');
                $colorMap = [
                    'zoom' => '#0073e6',
                    'google meet' => '#34a853',
                    'teams' => '#6b46c1',
                ];

                $color = $colorMap[$platform] ?? '#333';
                
                // Highlight selected job's interviews with a brighter color and border
                if ($isSelectedJob) {
                    $color = '#ff6b00'; // Orange/red for highlighting
                }

                $events[] = [
                    'id' => 'interview_' . $interview->getId(),
                    'title' => 'Interview: ' . ($jobTitle ?: 'Job Interview'),
                    'start' => $interviewDate->format('Y-m-d\TH:i:s'),
                    'url' => $interview->getMeetingUrl(),
                    'description' => ($jobTitle ? "Job: $jobTitle\n" : '') .
                        ($employerName ? "Employer: $employerName\n" : '') .
                        "Platform: " . ($interview->getMeetingPlatform() ?? 'Not specified'),
                    'platform' => $interview->getMeetingPlatform() ?? 'Not specified',
                    'job' => $jobTitle,
                    'employer' => $employerName,
                    'applicationStatus' => $application->getStatus(),
                    'color' => $color,
                    'type' => 'interview',
                    'applicationId' => $application->getId()->toString(),
                    'jobId' => $interviewJobId,
                    'isSelectedJob' => $isSelectedJob,
                    'className' => $isSelectedJob ? 'selected-job-interview' : '',
                    'display' => 'block', // Ensure interviews are displayed
                ];
            }

            return $this->json($events);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to load calendar data',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    private function getApplicationTimelineEvents($application, $isSelectedJob = false): array
    {
        $events = [];
        if (!$application) {
            return $events;
        }
        
        $job = $application->getJob();
        $jobTitle = $job?->getTitle() ?: 'Job';
        $employerName = $application->getEmployer()?->getName();
        $applicationId = $application->getId()->toString();
        $jobId = $job ? $job->getId()->toString() : null;
        
        // Applied date - always use createdAt as fallback since appliedAt might be null
        try {
            $appliedDate = $application->getAppliedAt();
            if (!$appliedDate) {
                $appliedDate = $application->getCreatedAt();
            }
        } catch (\Exception $e) {
            // If getAppliedAt() fails, use createdAt
            $appliedDate = $application->getCreatedAt();
        }
        
        // Always add applied event if we have a date
        if ($appliedDate) {
            $events[] = [
                'id' => 'applied_' . $applicationId,
                'title' => '✓ Applied: ' . $jobTitle,
                'start' => $appliedDate->format('Y-m-d'),
                'allDay' => true,
                'color' => $isSelectedJob ? '#ff6b00' : '#85BB65',
                'description' => "Applied for: $jobTitle" . ($employerName ? "\nEmployer: $employerName" : ''),
                'type' => 'timeline',
                'timelineStage' => 'applied',
                'applicationId' => $applicationId,
                'job' => $jobTitle,
                'jobId' => $jobId,
                'employer' => $employerName,
                'applicationStatus' => $application->getStatus(),
                'isSelectedJob' => $isSelectedJob,
                'className' => $isSelectedJob ? 'selected-job-event' : '',
            ];
        }
        
        // Interview date
        $interview = $application->getInterview();
        if ($interview && $interview->getDate()) {
            $events[] = [
                'id' => 'interview_timeline_' . $applicationId,
                'title' => '📅 Interview: ' . $jobTitle,
                'start' => $interview->getDate()->format('Y-m-d\TH:i:s'),
                'color' => $isSelectedJob ? '#ff6b00' : '#0073e6',
                'description' => "Interview for: $jobTitle" . ($employerName ? "\nEmployer: $employerName" : '') . 
                    ($interview->getMeetingPlatform() ? "\nPlatform: " . $interview->getMeetingPlatform() : ''),
                'type' => 'timeline',
                'timelineStage' => 'interview',
                'applicationId' => $applicationId,
                'job' => $jobTitle,
                'jobId' => $jobId,
                'employer' => $employerName,
                'applicationStatus' => $application->getStatus(),
                'isSelectedJob' => $isSelectedJob,
                'className' => $isSelectedJob ? 'selected-job-interview' : '',
            ];
        }
        
        // Negotiating status (if status is negotiating or offered)
        if (in_array($application->getStatus(), ['negotiating', 'offered'])) {
            $negotiatingDate = $application->getUpdatedAt() ?? $application->getCreatedAt();
            if ($negotiatingDate) {
                $events[] = [
                    'id' => 'negotiating_' . $applicationId,
                    'title' => '💼 Negotiating: ' . $jobTitle,
                    'start' => $negotiatingDate->format('Y-m-d'),
                    'allDay' => true,
                    'color' => $isSelectedJob ? '#ff6b00' : '#ffc107',
                    'description' => "Negotiating offer for: $jobTitle" . ($employerName ? "\nEmployer: $employerName" : ''),
                    'type' => 'timeline',
                    'timelineStage' => 'negotiating',
                    'applicationId' => $applicationId,
                    'job' => $jobTitle,
                    'jobId' => $jobId,
                'employer' => $employerName,
                'applicationStatus' => $application->getStatus(),
                    'isSelectedJob' => $isSelectedJob,
                    'className' => $isSelectedJob ? 'selected-job-event' : '',
                ];
            }
        }
        
        // Hired/Joining date
        if ($application->getHiredAt()) {
            $events[] = [
                'id' => 'hired_' . $applicationId,
                'title' => '🎉 Hired: ' . $jobTitle,
                'start' => $application->getHiredAt()->format('Y-m-d'),
                'allDay' => true,
                'color' => $isSelectedJob ? '#ff6b00' : '#28a745',
                'description' => "Hired for: $jobTitle" . ($employerName ? "\nEmployer: $employerName" : ''),
                'type' => 'timeline',
                'timelineStage' => 'joining',
                'applicationId' => $applicationId,
                'job' => $jobTitle,
                'jobId' => $jobId,
                'employer' => $employerName,
                'applicationStatus' => $application->getStatus(),
                'isSelectedJob' => $isSelectedJob,
                'className' => $isSelectedJob ? 'selected-job-event' : '',
            ];
        }
        
        // Completed status
        if ($application->getStatus() === 'completed') {
            $completedDate = $application->getUpdatedAt() ?? $application->getCreatedAt();
            if ($completedDate) {
                $events[] = [
                    'id' => 'completed_' . $applicationId,
                    'title' => '✅ Completed: ' . $jobTitle,
                    'start' => $completedDate->format('Y-m-d'),
                    'allDay' => true,
                    'color' => $isSelectedJob ? '#ff6b00' : '#6c757d',
                    'description' => "Completed: $jobTitle" . ($employerName ? "\nEmployer: $employerName" : ''),
                    'type' => 'timeline',
                    'timelineStage' => 'completed',
                    'applicationId' => $applicationId,
                    'job' => $jobTitle,
                    'jobId' => $jobId,
                'employer' => $employerName,
                'applicationStatus' => $application->getStatus(),
                    'isSelectedJob' => $isSelectedJob,
                    'className' => $isSelectedJob ? 'selected-job-event' : '',
                ];
            }
        }
        
        return $events;
    }
}

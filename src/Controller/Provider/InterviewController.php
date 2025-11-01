<?php

namespace App\Controller\Provider;

use App\Repository\InterviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/provider')]
class InterviewController extends AbstractController
{
    #[Route('/interviews/calendar', name: 'app_provider_interview_calendar')]
    public function calendarView()
    {
        return $this->render('provider/application/calendar.html.twig');
    }

    #[Route('/interviews/calendar-data', name: 'app_provider_interview_calendar_data')]
    public function calendarData(InterviewRepository $repository)
    {
        $interviews = $repository->getProviderInterviews($this->getUser()->getProvider()->getId());

        $events = [];

        foreach ($interviews as $interview) {
            $application = $interview->getApplication();
            $employerName = $application?->getEmployer()?->getName();
            $jobTitle = $application?->getJob()?->getTitle();

            // Color coding by platform (example)
            $platform = strtolower($interview->getMeetingPlatform());
            $colorMap = [
                'zoom' => '#0073e6',
                'google meet' => '#34a853',
                'teams' => '#6b46c1',
            ];

            $color = $colorMap[$platform] ?? '#333';

            $events[] = [
                'id' => $interview->getId(),
                'title' => $interview->getMeetingPlatform() .
                    ($employerName ? " - $employerName" : '') .
                    ($jobTitle ? " ($jobTitle)" : ''),
                'start' => $interview->getDate()->format('Y-m-d\TH:i:s'),
                'url' => $interview->getMeetingUrl(),
                'description' => ($jobTitle ? "Job: $jobTitle\n" : '') .
                    ($employerName ? "Employer: $employerName" : ''),
                'platform' => $interview->getMeetingPlatform(),
                'job' => $jobTitle,
                'color' => $color,
            ];
        }

        return $this->json($events);
    }
}

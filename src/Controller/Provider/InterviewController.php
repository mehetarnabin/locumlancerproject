<?php

namespace App\Controller\Provider;

use App\Repository\InterviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/provider')]
class InterviewController extends AbstractController
{
    #[Route('/interviews/calendar', name: 'app_provider_interview_calendar')]
    public function calendarView(Request $request)
    {
        $status = $request->query->get('status');
        $jobId = $request->query->get('jobId');
        $applicationId = $request->query->get('applicationId');
        return $this->render('provider/application/calendar.html.twig', [
            'status' => $status,
            'jobId' => $jobId,
            'applicationId' => $applicationId,
        ]);
    }

    #[Route('/interviews/calendar-data', name: 'provider_interview_calendar_data')]
    public function calendarData(Request $request, InterviewRepository $repository): JsonResponse
    {
        $provider = $this->getUser()->getProvider();
        if (!$provider) {
            return $this->json([]);
        }

        $jobId = $request->query->get('jobId');
        $applicationId = $request->query->get('applicationId');

        $interviews = $repository->getProviderInterviews($provider->getId());
        $events = [];

        foreach ($interviews as $interview) {
            $application = $interview->getApplication();
            $appId = $application ? (string) $application->getId() : null;
            $job = $application ? $application->getJob() : null;
            $jobIdVal = $job ? (string) $job->getId() : null;
            $jobTitle = $job ? (string) $job->getTitle() : null;

            if ($jobId && $jobIdVal && strcasecmp($jobId, $jobIdVal) !== 0) {
                continue;
            }
            if ($applicationId && $appId && strcasecmp($applicationId, $appId) !== 0) {
                continue;
            }

            $platform = $interview->getMeetingPlatform();
            $events[] = [
                'id' => (string) $interview->getId(),
                'type' => 'interview',
                'title' => $platform ?: 'Interview',
                'start' => $interview->getDate()->format('Y-m-d\TH:i:s'),
                'url' => $interview->getMeetingUrl(),
                'platform' => $platform,
                'applicationId' => $appId,
                'jobId' => $jobIdVal,
                'jobTitle' => $jobTitle,
            ];
        }

        return $this->json($events);
    }
}


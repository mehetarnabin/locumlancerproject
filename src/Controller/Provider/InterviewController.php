<?php

namespace App\Controller\Provider;

use App\Repository\InterviewRepository;
use App\Entity\Application;
use App\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/provider')]
class InterviewController extends AbstractController
{
    #[Route('/application/{id}/interview-details', name: 'app_provider_application_interview_details', methods: ['GET'])]
    public function getInterviewDetails(Application $application, InterviewRepository $interviewRepository): JsonResponse
    {
        $interviews = $interviewRepository->findBy(['application' => $application], ['date' => 'ASC']);

        if (empty($interviews)) {
            return $this->json(['success' => false, 'message' => 'No interview slots found']);
        }

        $data = array_map(function ($iv) {
            return [
                'id' => (string) $iv->getId(),
                'date' => $iv->getDate()->format('c'),
                'end_date' => $iv->getEndDate() ? $iv->getEndDate()->format('c') : null,
                'platform' => $iv->getMeetingPlatform(),
                'url' => $iv->getMeetingUrl()
            ];
        }, $interviews);

        return $this->json([
            'success' => true,
            'interviews' => $data
        ]);
    }

    #[Route('/application/{id}/interview-confirm', name: 'app_provider_application_interview_confirm', methods: ['POST'])]
    public function confirmInterview(
        Application $application,
        Request $request,
        EntityManagerInterface $em,
        InterviewRepository $interviewRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $interviewId = $data['interviewId'] ?? null;

        if ($interviewId) {
            $interview = $interviewRepository->find($interviewId);
            if ($interview && $interview->getApplication() === $application) {
                $application->setInterview($interview);
                $em->persist($application);
            }
        }

        $user = $this->getUser();

        // Create a confirmation document/record
        $document = new Document();
        $timestamp = (new \DateTime())->format('Y-m-d H:i:s');
        $document->setName('Interview Confirmation');
        $document->setFileName("Interview Confirmed - {$timestamp}");
        $document->setCategory('Interview Confirmation');
        $document->setUser($user);
        $document->setProvider($user); // Provider field is type User in Document entity
        $document->setApplication($application);
        $document->setMimeType('application/x-confirmation');
        $document->setFilePath(null); // No physical file

        $em->persist($document);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Interview confirmed']);
    }

    #[Route('/application/{id}/interview-request-change', name: 'app_provider_application_interview_request_change', methods: ['POST'])]
    public function requestChange(
        Application $application,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $comment = $data['comment'] ?? '';

        if (empty($comment)) {
            return $this->json(['success' => false, 'message' => 'Comment is required']);
        }

        $user = $this->getUser();

        $document = new Document();
        $timestamp = (new \DateTime())->format('Y-m-d H:i:s');
        $document->setName('Interview Change Request');
        $document->setFileName("Change Request - {$timestamp}");
        $document->setCategory('Interview Reschedule Request');
        $document->setDescription($comment);
        $document->setUser($user);
        $document->setProvider($user);
        $document->setApplication($application);
        $document->setMimeType('text/plain');
        $document->setFilePath(null);

        $em->persist($document);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Request sent successfully']);
    }

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
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $provider = $user->getProvider();
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
                'end' => $interview->getEndDate() ? $interview->getEndDate()->format('Y-m-d\TH:i:s') : null,
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

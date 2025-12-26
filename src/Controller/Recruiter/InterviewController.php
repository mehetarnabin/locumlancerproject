<?php

namespace App\Controller\Recruiter;

use App\Repository\InterviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/recruiter')]
class InterviewController extends AbstractController
{
    #[Route('/interviews/', name: 'app_recruiter_interviews')]
    public function index()
    {
        return $this->redirectToRoute('app_recruiter_interview_calendar');
    }

    #[Route('/interviews/calendar', name: 'app_recruiter_interview_calendar')]
    public function calendarView()
    {
        return $this->render('recruiter/application/calendar.html.twig');
    }

    #[Route('/interviews/calendar-data', name: 'app_recruiter_interview_calendar_data')]
    public function calendarData(InterviewRepository $repository, \Doctrine\ORM\EntityManagerInterface $em)
    {
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();

        if (!$recruiter) {
            return $this->json([]);
        }

        // Fetch interviews for applications to jobs assigned to this recruiter
        // Assuming InterviewRepository doesn't have a dedicated method yet, we can build a query or use existing logic if adapted.
        // Let's try to find interviews where application.job is in recruiter's jobs.

        $interviews = $em->getRepository(\App\Entity\Interview::class)->createQueryBuilder('i')
            ->join('i.application', 'a')
            ->join('a.job', 'j')
            ->join('j.jobRecruiters', 'jr')
            ->where('jr.recruiter = :recruiter')
            ->setParameter('recruiter', $recruiter)
            ->getQuery()
            ->getResult();

        $events = [];

        foreach ($interviews as $interview) {
            $application = $interview->getApplication();
            $applicantName = $application?->getProvider()?->getName();
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
                    ($applicantName ? " - $applicantName" : '') .
                    ($jobTitle ? " ($jobTitle)" : ''),
                'start' => $interview->getDate()->format('Y-m-d\TH:i:s'),
                'url' => $interview->getMeetingUrl(),
                'description' => ($jobTitle ? "Job: $jobTitle\n" : '') .
                    ($applicantName ? "Applicant: $applicantName" : ''),
                'platform' => $interview->getMeetingPlatform(),
                'job' => $jobTitle,
                'color' => $color,
            ];
        }

        return $this->json($events);
    }
}

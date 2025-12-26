<?php

namespace App\Controller\Recruiter;

use App\Entity\Application;
use App\Entity\Invoice;
use App\Entity\Job;
use App\Entity\Message;
use App\Entity\Notification;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/recruiter')]
class OfficeController extends AbstractController
{
    #[Route('/office', name: 'app_recruiter_office')]
    public function index(
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();

        if (!$recruiter) {
            return $this->redirectToRoute('app_login');
        }

        // Get recruiter's active jobs
        $currentJobs = [];
        foreach ($recruiter->getJobRecruiters() as $jr) {
            $job = $jr->getJob();
            if ($job->getStatus() === Job::JOB_STATUS_PUBLISHED) {
                $currentJobs[] = $job;
            }
        }

        // Get recruiter's checking invoices (assuming relation exists or placeholder)
        // $pendingInvoices = $em->getRepository(Invoice::class)->findBy(['recruiter' => $recruiter, 'status' => Invoice::INVOICE_STATUS_PENDING], ['createdAt' => 'DESC']);
        $pendingInvoices = []; // Placeholder to avoid error until Invoice relation is verified

        return $this->render('recruiter/office/office.html.twig', [
            'currentJobs' => $currentJobs,
            'pendingInvoices' => $pendingInvoices,
        ]);
    }
}

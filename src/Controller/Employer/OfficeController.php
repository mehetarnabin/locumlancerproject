<?php

namespace App\Controller\Employer;

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

#[Route('/employer')]
class OfficeController extends AbstractController
{
    #[Route('/office', name: 'app_employer_office')]
    public function index(
        EntityManagerInterface $em,
    ): Response
    {
        $user = $this->getUser();

        $employer = $this->getUser()->getEmployer();
        $currentJobs = $em->getRepository(Job::class)->getEmployerCurrentJobs($employer->getId());
        $pendingInvoices = $em->getRepository(Invoice::class)->findBy(['employer' => $employer, 'status' => Invoice::INVOICE_STATUS_PENDING], ['createdAt' => 'DESC']);

        return $this->render('employer/office/office.html.twig', [
            'currentJobs' => $currentJobs,
            'pendingInvoices' => $pendingInvoices,
        ]);
    }
}
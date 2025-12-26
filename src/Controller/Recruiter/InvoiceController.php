<?php

namespace App\Controller\Recruiter;

use App\Entity\Cashback;
use App\Entity\Employer;
use App\Entity\Invoice;
use App\Event\InvoiceEvent;
use App\Service\ConfigManager;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Stripe;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/recruiter')]
class InvoiceController extends AbstractController
{
    #[Route('/invoices', name: 'app_recruiter_invoices')]
    public function index(EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();
        if (!$recruiter) {
            throw $this->createAccessDeniedException('Recruiter account not found.');
        }

        $invoices = $em->getRepository(Invoice::class)->findBy(['recruiter' => $recruiter], ['createdAt' => 'DESC']);
        return $this->render('recruiter/invoice/index.html.twig', [
            'invoices' => $invoices,
        ]);
    }

    #[Route('/invoices/pending', name: 'app_recruiter_invoices_pending')]
    public function pending(EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();
        if (!$recruiter) {
            throw $this->createAccessDeniedException('Recruiter account not found.');
        }

        $invoices = $em->getRepository(Invoice::class)->findBy(['recruiter' => $recruiter, 'status' => Invoice::INVOICE_STATUS_PENDING], ['createdAt' => 'DESC']);
        return $this->render('recruiter/invoice/pending-invoices.html.twig', [
            'invoices' => $invoices,
        ]);
    }

    #[Route('/invoices/paid', name: 'app_recruiter_invoices_paid')]
    public function paid(EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();
        if (!$recruiter) {
            throw $this->createAccessDeniedException('Recruiter account not found.');
        }

        $invoices = $em->getRepository(Invoice::class)->findBy(['recruiter' => $recruiter, 'status' => Invoice::INVOICE_STATUS_PAID], ['createdAt' => 'DESC']);
        return $this->render('recruiter/invoice/paid-invoices.html.twig', [
            'invoices' => $invoices,
        ]);
    }

    #[Route('/invoices/{id}/show', name: 'app_recruiter_invoice_show')]
    public function show(Invoice $invoice): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();

        if ($invoice->getRecruiter() !== $recruiter) {
            $this->addFlash('error', "You don't have access to this invoice.");
            return $this->redirectToRoute('app_recruiter_invoices');
        }

        // Commission calculation (Example: 10% if not defined elsewhere)
        // You might want to fetch this from JobRecruiter or Config
        $commissionRate = 0.10; // Default 10%
        $commissionAmount = $invoice->getAmount() * $commissionRate;

        return $this->render('recruiter/invoice/detail.html.twig', [
            'invoice' => $invoice,
            'commissionAmount' => $commissionAmount,
        ]);
    }

    #[Route('/invoices/{id}/print', name: 'app_recruiter_invoice_print')]
    public function print(Invoice $invoice): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();

        if ($invoice->getRecruiter() !== $recruiter) {
            $this->addFlash('error', "You don't have access to this invoice.");
            return $this->redirectToRoute('app_recruiter_invoices');
        }

        $commissionRate = 0.10;
        $commissionAmount = $invoice->getAmount() * $commissionRate;

        return $this->render('recruiter/invoice/print.html.twig', [
            'invoice' => $invoice,
            'commissionAmount' => $commissionAmount,
        ]);
    }
}

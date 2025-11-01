<?php

namespace App\Controller\Admin;

use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class InvoiceController extends AbstractController
{
    #[Route('/invoices', name: 'app_admin_invoices')]
    public function index(EntityManagerInterface $em): Response
    {
        $invoices = $em->getRepository(Invoice::class)->findBy([], ['createdAt' => 'DESC']);
        return $this->render('admin/invoice/index.html.twig', [
            'invoices' => $invoices,
        ]);
    }

    #[Route('/invoices/{id}/show', name: 'app_admin_invoice_show')]
    public function show(Invoice $invoice): Response
    {
        return $this->render('admin/invoice/detail.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    #[Route('/invoices/pending', name: 'app_admin_invoices_pending')]
    public function pending(EntityManagerInterface $em): Response
    {
        $invoices = $em->getRepository(Invoice::class)->findBy(['status' => Invoice::INVOICE_STATUS_PENDING], ['createdAt' => 'DESC']);
        return $this->render('admin/invoice/pending-invoices.html.twig', [
            'invoices' => $invoices,
        ]);
    }

    #[Route('/invoices/paid', name: 'app_admin_invoices_paid')]
    public function paid(EntityManagerInterface $em): Response
    {
        $invoices = $em->getRepository(Invoice::class)->findBy(['status' => Invoice::INVOICE_STATUS_PAID], ['createdAt' => 'DESC']);
        return $this->render('admin/invoice/paid-invoices.html.twig', [
            'invoices' => $invoices,
        ]);
    }
}
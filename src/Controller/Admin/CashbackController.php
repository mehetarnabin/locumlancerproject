<?php

namespace App\Controller\Admin;

use App\Entity\Cashback;
use App\Repository\CashbackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/cashback')]
class CashbackController extends AbstractController
{
    #[Route('/cashback', name: 'app_admin_cashback')]
    public function index(CashbackRepository $cashbackRepository, Request $request): Response
    {
        $offset = $request->query->get('page', 1);
        $perPage = $request->get('per_page', 10);
        $filters = $request->query->all();

        $cashbacks = $cashbackRepository->getAll($offset, $perPage, $filters);

        return $this->render('admin/cashback/index.html.twig', [
            'cashbacks' => $cashbacks,
        ]);
    }

    #[Route('/{id}/detail', name: 'app_admin_cashback_detail')]
    public function show(Cashback $cashback)
    {
        return $this->render('admin/cashback/show.html.twig', [
            'cashback' => $cashback,
        ]);
    }

    #[Route('/admin/cashback/mark-paid', name: 'app_admin_cashback_mark_paid', methods: ['POST'])]
    public function markAsPaid(Request $request, CashbackRepository $cashbackRepository, EntityManagerInterface $em): JsonResponse
    {
        $cashbackId = $request->request->get('cashback_id');
        $attachmentFile = $request->files->get('attachment');

        if (!$cashbackId || !$attachmentFile) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid input.']);
        }

        $cashback = $cashbackRepository->find($cashbackId);
        if (!$cashback) {
            return new JsonResponse(['success' => false, 'message' => 'Cashback not found.']);
        }

        // Upload file
        $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/cashbacks';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $filename = uniqid().'.'.$attachmentFile->guessExtension();
        $attachmentFile->move($uploadsDir, $filename);

        $cashback->setStatus(Cashback::CASHBACK_STATUS_PAID);
        $cashback->setAttachment($filename);
        $cashback->setPaidDate(new \DateTime());

        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}
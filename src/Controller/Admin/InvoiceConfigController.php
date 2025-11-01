<?php
namespace App\Controller\Admin;

use App\Service\ConfigManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class InvoiceConfigController extends AbstractController
{
    #[Route('/admin/config/invoice', name: 'app_admin_config_invoice', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ConfigManager $configManager,
        EntityManagerInterface $em
    )
    {
        if ($request->getMethod() == 'POST') {
            // General
            $configManager->set('flat_fee_amount', $request->get('flat_fee_amount'));
            $configManager->set('cashback_percent', $request->get('cashback_percent'));


            $em->flush();
            $this->addFlash('success', "Invoice configuration updated successfully.");

            return $this->redirectToRoute('app_admin_config_invoice');
        }

        return $this->render("admin/config/invoice.html.twig", [

        ]);
    }
}
<?php
namespace App\Controller\Admin;

use App\Service\ConfigManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ConfigController extends AbstractController
{
    #[Route('/admin/config', name: 'app_admin_config', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ConfigManager $configManager,
        EntityManagerInterface $em
    )
    {
        if ($request->getMethod() == 'POST') {
            // General
            $configManager->set('brand_name', $request->get('brand_name'));
            $configManager->set('legal_name', $request->get('legal_name'));
            $configManager->set('slogan', $request->get('slogan'));
            $configManager->set('notification_receiving_emails', $request->get('notification_receiving_emails'));

            // Meta
            $configManager->set('meta_title', $request->get('meta_title'));
            $configManager->set('meta_keywords', $request->get('meta_keywords'));
            $configManager->set('meta_description', $request->get('meta_description'));

            // Contact
            $configManager->set('office_address', $request->get('office_address'));
            $configManager->set('office_email', $request->get('office_email'));
            $configManager->set('office_phone1', $request->get('office_phone1'));
            $configManager->set('office_phone2', $request->get('office_phone2'));
            $configManager->set('office_google_map', $request->get('office_google_map'));

            // Social Media
            $configManager->set('facebook_url', $request->get('facebook_url'));
            $configManager->set('twitter_url', $request->get('twitter_url'));
            $configManager->set('instagram_url', $request->get('instagram_url'));
            $configManager->set('youtube_url', $request->get('youtube_url'));
            $configManager->set('linkedin_url', $request->get('linkedin_url'));

            // Scripts
            $configManager->set('google_analytics_code', $request->get('google_analytics_code'));
            $configManager->set('custom_header_script', $request->get('custom_header_script'));
            $configManager->set('custom_footer_script', $request->get('custom_footer_script'));

            if ($image = $request->files->get("brand_logo")) {
                if ($image instanceof UploadedFile) {
                    $name = $image->getClientOriginalName();
                    $dir = $this->getParameter('kernel.project_dir') . "/public/uploads";
                    $image->move($dir, $name);

                    $configManager->set('brand_logo', "/uploads/" . $name);
                }
            }

            $em->flush();
            $this->addFlash('success', "Configuration updated successfully.");

            return $this->redirectToRoute('app_admin_config');
        }

        return $this->render("admin/config/index.html.twig", [

        ]);
    }
}
<?php

namespace App\Controller\Recruiter;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/recruiter')]
class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'app_recruiter_settings')]
    public function index(): Response
    {
        return $this->render('recruiter/settings/index.html.twig', [
            'controller_name' => 'SettingsController',
        ]);
    }
}

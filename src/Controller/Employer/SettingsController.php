<?php

namespace App\Controller\Employer;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/employer')]
class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'app_employer_settings')]
    public function index(): Response
    {
        return $this->render('employer/settings/index.html.twig', [
            'controller_name' => 'SettingsController',
        ]);
    }
}


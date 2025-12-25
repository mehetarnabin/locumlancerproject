<?php

namespace App\Controller\Employer;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/employer')]
class RecruitersConnectController extends AbstractController
{
    #[Route('/recruiters-connect', name: 'app_employer_recruiters_connect')]
    public function index(): Response
    {
        return $this->render('employer/recruiters_connect/index.html.twig', [
            'controller_name' => 'RecruitersConnectController',
        ]);
    }
}


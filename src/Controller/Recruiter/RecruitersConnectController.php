<?php

namespace App\Controller\Recruiter;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/recruiter')]
class RecruitersConnectController extends AbstractController
{
    #[Route('/recruiters-connect', name: 'app_recruiter_recruiters_connect')]
    public function index(): Response
    {
        return $this->render('recruiter/recruiters_connect/index.html.twig', [
            'controller_name' => 'RecruitersConnectController',
        ]);
    }
}

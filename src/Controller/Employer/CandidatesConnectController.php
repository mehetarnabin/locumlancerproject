<?php

namespace App\Controller\Employer;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/employer')]
class CandidatesConnectController extends AbstractController
{
    #[Route('/candidates-connect', name: 'app_employer_candidates_connect')]
    public function index(): Response
    {
        return $this->render('employer/candidates_connect/index.html.twig', [
            'controller_name' => 'CandidatesConnectController',
        ]);
    }
}


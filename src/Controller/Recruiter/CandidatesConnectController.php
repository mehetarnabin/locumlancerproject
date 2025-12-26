<?php

namespace App\Controller\Recruiter;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/recruiter')]
class CandidatesConnectController extends AbstractController
{
    #[Route('/candidates-connect', name: 'app_recruiter_candidates_connect')]
    public function index(): Response
    {
        return $this->render('recruiter/candidates_connect/index.html.twig', [
            'controller_name' => 'CandidatesConnectController',
        ]);
    }
}

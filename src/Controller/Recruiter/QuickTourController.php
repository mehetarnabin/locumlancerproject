<?php

namespace App\Controller\Recruiter;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/recruiter')]
class QuickTourController extends AbstractController
{
    #[Route('/quick-tour', name: 'app_recruiter_quick_tour')]
    public function index(): Response
    {
        return $this->render('recruiter/quick_tour/index.html.twig', [
            'controller_name' => 'QuickTourController',
        ]);
    }
}

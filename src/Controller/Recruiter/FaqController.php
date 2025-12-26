<?php

namespace App\Controller\Recruiter;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/recruiter')]
class FaqController extends AbstractController
{
    #[Route('/faq', name: 'app_recruiter_faq')]
    public function index(): Response
    {
        return $this->render('recruiter/faq/index.html.twig', []);
    }
}

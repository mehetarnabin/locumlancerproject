<?php

namespace App\Controller\Recruiter;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/recruiter')]
class MembershipController extends AbstractController
{
    #[Route('/membership', name: 'app_recruiter_membership')]
    public function index(): Response
    {
        return $this->render('recruiter/membership/membership.html.twig', [
            'controller_name' => 'MembershipController',
        ]);
    }
}

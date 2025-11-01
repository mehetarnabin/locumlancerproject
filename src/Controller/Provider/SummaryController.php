<?php

namespace App\Controller\Provider;

use App\Entity\Education;
use App\Entity\Experience;
use App\Entity\Insurance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/provider')]
class SummaryController extends AbstractController
{
    #[Route('/summary', name: 'app_provider_summary')]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $provider = $this->getUser()->getProvider();

        $educations = $em->getRepository(Education::class)->findBy(['user' => $user]);
        $experiences = $em->getRepository(Experience::class)->findBy(['user' => $user]);
        $insurances = $em->getRepository(Insurance::class)->findBy(['user' => $user]);

        return $this->render('provider/summary/index.html.twig', [
            'user' => $user,
            'provider' => $provider,
            'educations' => $educations,
            'experiences' => $experiences,
            'insurances' => $insurances,
        ]);
    }
}
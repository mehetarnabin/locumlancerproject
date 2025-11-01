<?php

namespace App\Controller;

use App\Entity\Job;
use App\Repository\JobRepository;
use App\Repository\SpecialityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(JobRepository $jobRepository): Response
    {
        $jobs = $jobRepository->findBy(['status' => Job::JOB_STATUS_PUBLISHED], ['createdAt' => 'DESC']);
        return $this->render('default/index.html.twig', [
            'jobs' => $jobs,
        ]);
    }

    #[Route('/for-providers', name: 'app_for_providers')]
    public function providers(): Response
    {
        return $this->render('default/providers.html.twig', []);
    }

    #[Route('/for-employers', name: 'app_for_employers')]
    public function employers(): Response
    {
        return $this->render('default/employers.html.twig', []);
    }

    #[Route('/locumbook', name: 'app_locumbook')]
    public function locumbook(): Response
    {
        return $this->render('default/locumbook.html.twig', []);
    }

    #[Route('/locumwall', name: 'app_locumwall')]
    public function locumwall(): Response
    {
        return $this->render('default/locumwall.html.twig', []);
    }

    #[Route('/jokes-apart', name: 'app_jokes_apart')]
    public function jokesApart(): Response
    {
        return $this->render('default/jokes-apart.html.twig', []);
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('default/contact.html.twig', []);
    }

    #[Route('/about', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('default/about.html.twig', []);
    }

    #[Route('/privacy-policy', name: 'app_privacy_policy')]
    public function privacy(): Response
    {
        return $this->render('default/privacy-policy.html.twig', []);
    }

    #[Route('/terms-and-conditions', name: 'app_terms_and_conditions')]
    public function terms(): Response
    {
        return $this->render('default/terms-and-conditions.html.twig', []);
    }

    #[Route('/get-specialities/{professionId}', name: 'get_specialties', methods: ['GET'])]
    public function getSpecialties($professionId, SpecialityRepository $specialtyRepository): JsonResponse
    {
        $specialties = $specialtyRepository->findBy(['profession' => $professionId], ['name' => 'ASC']);

        $data = array_map(fn($specialty) => [
            'id' => $specialty->getId(),
            'name' => $specialty->getName()
        ], $specialties);

        return new JsonResponse($data);
    }

    #[Route('/test-email-template', name: 'get_test_email_template')]
    public function testEmailTemplate()
    {
        return $this->render('emails/test-template.html.twig', ['token' => 'abc']);
    }

    #[Route('/resources', name: 'app_resources')]
    public function resources(): Response
    {
        return $this->render('default/resources.html.twig', []);
    }
}

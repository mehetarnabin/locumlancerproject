<?php

namespace App\Controller\Provider;

use App\Entity\Education;
use App\Form\EducationType;
use App\Repository\EducationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/provider/application/education')]
class EducationController extends AbstractController
{
    #[Route('/', name: 'app_provider_education_index', methods: ['GET', 'POST'])]
    public function index(EducationRepository $educationRepository, Request $request, EntityManagerInterface $entityManager): Response
    {
        $education = new Education();
        $education->setUser($this->getUser());
        $form = $this->createForm(EducationType::class, $education);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($education);
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json(['status' => 'success']);
            }

            $this->addFlash('success', 'Education & training added successfully');
            return $this->redirectToRoute('app_provider_education_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('provider/education/index.html.twig', [
            'educations' => $educationRepository->findBy(['user' => $this->getUser()], ['startDate' => 'DESC']),
            'education' => $education,
            'form' => $form->createView(),
            'countries' => Countries::getNames()
        ]);
    }

    #[Route('/list', name: 'app_provider_education_list', methods: ['GET'])]
    public function list(EducationRepository $educationRepository, Request $request): Response
    {
        $educations = $educationRepository->findBy(['user' => $this->getUser()], ['startDate' => 'DESC']);
        
        if ($request->isXmlHttpRequest()) {
            return $this->render('provider/profile/_education_list.html.twig', [
                'educations' => $educations
            ]);
        }
        
        return $this->json(['educations' => array_map(function($edu) {
            return [
                'id' => $edu->getId(),
                'school' => $edu->getSchool(),
                'degree' => $edu->getDegree(),
                'country' => $edu->getCountry(),
                'state' => $edu->getState(),
                'city' => $edu->getCity(),
                'startDate' => $edu->getStartDate()?->format('m/d/Y'),
                'endDate' => $edu->getEndDate()?->format('m/d/Y'),
            ];
        }, $educations)]);
    }

    #[Route('/new', name: 'app_provider_education_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $education = new Education();
        $education->setUser($this->getUser());
        $form = $this->createForm(EducationType::class, $education);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($education);
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json(['status' => 'success']);
            }

            $this->addFlash('success', 'Education & training added successfully');
            return $this->redirectToRoute('app_provider_education_index', [], Response::HTTP_SEE_OTHER);
        }

        // For AJAX requests, return just the form HTML
        if ($request->isXmlHttpRequest()) {
            if ($form->isSubmitted() && !$form->isValid()) {
                // Return form with validation errors
                return $this->render('provider/education/_form.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
            // Return empty form for GET requests
            return $this->render('provider/education/_form.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        return $this->render('provider/education/new.html.twig', [
            'education' => $education,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_provider_education_show', methods: ['GET'])]
    public function show(Education $education, Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'id' => $education->getId(),
                'country' => $education->getCountry(),
                'state' => $education->getState(),
                'city' => $education->getCity(),
                'school' => $education->getSchool(),
                'degree' => $education->getDegree(),
                'fieldOfStudy' => $education->getFieldOfStudy(),
                'startDate' => $education->getStartDate()?->format('Y-m-d'),
                'endDate' => $education->getEndDate()?->format('Y-m-d'),
                'grade' => $education->getGrade(),
                'description' => $education->getDescription(),
            ]);
        }

        return $this->render('provider/education/show.html.twig', [
            'education' => $education,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_provider_education_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Education $education, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(EducationType::class, $education, [
            'action' => $this->generateUrl('app_provider_education_edit', ['id' => $education->getId()]),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'form' => $this->renderView('provider/education/_form.html.twig', [
                    'form' => $form->createView(),
                ])
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json(['status' => 'success']);
            }

            $this->addFlash('success', 'Education & training updated successfully');

            if($request->request->has('save_continue')){
                return $this->redirectToRoute('app_provider_license_index');
            }

            return $this->redirectToRoute('app_provider_education_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('provider/education/edit.html.twig', [
            'education' => $education,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_provider_education_delete', methods: ['GET'])]
    public function delete(Request $request, Education $education, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($education);
        $entityManager->flush();

        if ($request->isXmlHttpRequest()) {
            return $this->json(['status' => 'success']);
        }

        $this->addFlash('success', 'Education & training deleted successfully');
        return $this->redirectToRoute('app_provider_education_index', [], Response::HTTP_SEE_OTHER);
    }
}

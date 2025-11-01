<?php

namespace App\Controller\Provider;

use App\Entity\License;
use App\Form\LicenseType;
use App\Repository\LicenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/provider/application/license')]
class LicenseController extends AbstractController
{
    #[Route('/', name: 'app_provider_license_index', methods: ['GET', 'POST'])]
    public function index(LicenseRepository $licenseRepository, Request $request, EntityManagerInterface $entityManager): Response
    {
        $license = new License();
        $license->setUser($this->getUser());
        $form = $this->createForm(LicenseType::class, $license);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($license);
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json(['status' => 'success']);
            }

            $this->addFlash('success', 'License & certification added successfully');
            return $this->redirectToRoute('app_provider_license_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('provider/license/index.html.twig', [
            'licenses' => $licenseRepository->findBy(['user' => $this->getUser()], ['issueDate' => 'DESC']),
            'license' => $license,
            'form' => $form->createView(),
            'countries' => Countries::getNames()
        ]);
    }

    #[Route('/new', name: 'app_provider_license_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $license = new License();
        $license->setUser($this->getUser());
        $form = $this->createForm(LicenseType::class, $license);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($license);
            $entityManager->flush();

            $this->addFlash('success', 'License & certification added successfully');
            return $this->redirectToRoute('app_provider_license_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('provider/license/new.html.twig', [
            'license' => $license,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_provider_license_show', methods: ['GET'])]
    public function show(License $license, Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'id' => $license->getId(),
                'country' => $license->getCountry(),
                'state' => $license->getState(),
                'city' => $license->getCity(),
                'school' => $license->getSchool(),
                'degree' => $license->getDegree(),
                'fieldOfStudy' => $license->getFieldOfStudy(),
                'startDate' => $license->getStartDate()?->format('Y-m-d'),
                'endDate' => $license->getEndDate()?->format('Y-m-d'),
                'grade' => $license->getGrade(),
                'description' => $license->getDescription(),
            ]);
        }

        return $this->render('provider/license/show.html.twig', [
            'license' => $license,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_provider_license_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, License $license, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LicenseType::class, $license, [
            'action' => $this->generateUrl('app_provider_license_edit', ['id' => $license->getId()]),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'form' => $this->renderView('provider/license/_form.html.twig', [
                    'form' => $form->createView(),
                ])
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json(['status' => 'success']);
            }

            $this->addFlash('success', 'License & certification updated successfully');

            if($request->request->has('save_continue')){
                return $this->redirectToRoute('app_provider_experience_index');
            }

            return $this->redirectToRoute('app_provider_license_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('provider/license/edit.html.twig', [
            'license' => $license,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_provider_license_delete', methods: ['GET'])]
    public function delete(Request $request, License $license, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($license);
        $entityManager->flush();

        $this->addFlash('success', 'License & training deleted successfully');
        return $this->redirectToRoute('app_provider_license_index', [], Response::HTTP_SEE_OTHER);
    }
}

<?php

namespace App\Controller\Admin;

use App\Entity\Speciality;
use App\Form\SpecialityType;
use App\Repository\SpecialityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/config/speciality')]
final class SpecialityController extends AbstractController
{
    #[Route(name: 'app_admin_speciality_index', methods: ['GET'])]
    public function index(SpecialityRepository $specialityRepository): Response
    {
        return $this->render('admin/speciality/index.html.twig', [
            'specialities' => $specialityRepository->getAll(),
        ]);
    }

    #[Route('/new', name: 'app_admin_speciality_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $speciality = new Speciality();
        $form = $this->createForm(SpecialityType::class, $speciality);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($speciality);
            $entityManager->flush();

            $this->addFlash('success', 'Speciality created.');
            return $this->redirectToRoute('app_admin_speciality_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/speciality/new.html.twig', [
            'speciality' => $speciality,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_speciality_show', methods: ['GET'])]
    public function show(Speciality $speciality): Response
    {
        return $this->render('admin/speciality/show.html.twig', [
            'speciality' => $speciality,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_speciality_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Speciality $speciality, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SpecialityType::class, $speciality);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Speciality updated.');
            return $this->redirectToRoute('app_admin_speciality_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/speciality/edit.html.twig', [
            'speciality' => $speciality,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_speciality_delete', methods: ['GET'])]
    public function delete(Request $request, Speciality $speciality, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($speciality);
        $entityManager->flush();

        $this->addFlash('success', 'Speciality deleted.');
        return $this->redirectToRoute('app_admin_speciality_index', [], Response::HTTP_SEE_OTHER);
    }
}

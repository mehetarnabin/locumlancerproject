<?php

namespace App\Controller\Admin;

use App\Entity\Profession;
use App\Form\ProfessionType;
use App\Repository\ProfessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/config/profession')]
final class ProfessionController extends AbstractController
{
    #[Route(name: 'app_admin_profession_index', methods: ['GET'])]
    public function index(ProfessionRepository $professionRepository): Response
    {
        return $this->render('admin/profession/index.html.twig', [
            'professions' => $professionRepository->findBy([], ['position' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_admin_profession_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ProfessionRepository $professionRepository): Response
    {
        $profession = new Profession();
        $form = $this->createForm(ProfessionType::class, $profession);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $maxPosition = $professionRepository->createQueryBuilder('p')
                ->select('MAX(p.position)')
                ->getQuery()
                ->getSingleScalarResult();

            $profession->setPosition((int)$maxPosition + 1);

            $entityManager->persist($profession);

            $entityManager->flush();

            $this->addFlash('success', 'Profession created.');
            return $this->redirectToRoute('app_admin_profession_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/profession/new.html.twig', [
            'profession' => $profession,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_profession_show', methods: ['GET'])]
    public function show(Profession $profession): Response
    {
        return $this->render('admin/profession/show.html.twig', [
            'profession' => $profession,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_profession_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Profession $profession, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProfessionType::class, $profession);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Profession updated.');
            return $this->redirectToRoute('app_admin_profession_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/profession/edit.html.twig', [
            'profession' => $profession,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_profession_delete', methods: ['GET'])]
    public function delete(Request $request, Profession $profession, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($profession);
        $entityManager->flush();

        $this->addFlash('success', 'Profession deleted.');
        return $this->redirectToRoute('app_admin_profession_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/reorder', name: 'app_admin_profession_reorder', methods: ['POST'])]
    public function reorder(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        foreach ($data as $item) {
            $profession = $em->getRepository(Profession::class)->find($item['id']);
            if ($profession) {
                $profession->setPosition($item['position']);
            }
        }

        $em->flush();

        return new JsonResponse(['status' => 'ok']);
    }
}

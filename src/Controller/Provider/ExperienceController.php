<?php

namespace App\Controller\Provider;

use App\Entity\Experience;
use App\Form\ExperienceType;
use App\Repository\ExperienceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/provider/application/experience')]
class ExperienceController extends AbstractController
{
    #[Route('/', name: 'app_provider_experience_index', methods: ['GET', 'POST'])]
    public function index(ExperienceRepository $experienceRepository, Request $request, EntityManagerInterface $entityManager): Response
    {
        $experience = new Experience();
        $experience->setUser($this->getUser());
        $form = $this->createForm(ExperienceType::class, $experience);
        $form->handleRequest($request);

        // Handle form submission if it's sent directly from the modal
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($experience);
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json(['status' => 'success']);
            }

            $this->addFlash('success', 'Work history added successfully');
            return $this->redirectToRoute('app_provider_experience_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('provider/experience/index.html.twig', [
            'experiences' => $experienceRepository->findBy(['user' => $this->getUser()], ['startDate' => 'DESC']),
            'experience' => $experience,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/new', name: 'app_provider_experience_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $experience = new Experience();
        $experience->setUser($this->getUser());
        $form = $this->createForm(ExperienceType::class, $experience);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($experience);
            $entityManager->flush();

            return $this->redirectToRoute('app_provider_experience_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('provider/experience/new.html.twig', [
            'experience' => $experience,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_provider_experience_show', methods: ['GET'])]
    public function show(Experience $experience, Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'id' => $experience->getId(),
                'title' => $experience->getTitle(),
                'employmentType' => $experience->getEmploymentType(),
                'company' => $experience->getCompany(),
                'startDate' => $experience->getStartDate()?->format('Y-m-d'),
                'endDate' => $experience->getEndDate()?->format('Y-m-d'),
                'location' => $experience->getLocation(),
                'locationType' => $experience->getLocationType(),
                'description' => $experience->getDescription(),
            ]);
        }

        return $this->render('provider/experience/show.html.twig', [
            'experience' => $experience,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_provider_experience_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Experience $experience, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ExperienceType::class, $experience, [
            'action' => $this->generateUrl('app_provider_experience_edit', ['id' => $experience->getId()]),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['status' => 'success']);
            }

            $this->addFlash('success', 'Work history updated successfully');

            if($request->request->has('save_continue')){
                return $this->redirectToRoute('app_provider_insurance_index');
            }

            return $this->redirectToRoute('app_provider_experience_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'form' => $this->renderView('provider/experience/_form.html.twig', [
                    'form' => $form->createView(),
                ])
            ]);
        }

        return $this->render('provider/experience/edit.html.twig', [
            'form' => $form->createView(),
            'experience' => $experience,
        ]);
    }

    #[Route('/{id}/update', name: 'app_provider_experience_update', methods: ['POST'])]
    public function updateExperience(Request $request, ExperienceRepository $experienceRepository, Uuid $id, EntityManagerInterface $em): JsonResponse
    {
        $experience = $experienceRepository->find($id);

        if (!$experience) {
            return new JsonResponse(['error' => 'Experience not found'], 404);
        }

        $data = $request->getPayload()->all();

        $experience->setTitle($data['title']);
        $experience->setEmploymentType($data['employmentType']);
        $experience->setStartDate(new \DateTime($data['startDate']));
        $experience->setEndDate(new \DateTime($data['endDate']));
        $experience->setLocation($data['location']);
        $experience->setLocationType($data['locationType']);
        $experience->setDescription($data['description']);

        $em->persist($experience);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id}', name: 'app_provider_experience_delete', methods: ['POST'])]
    public function delete(Request $request, Experience $experience, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$experience->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($experience);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_provider_experience_index', [], Response::HTTP_SEE_OTHER);
    }
}

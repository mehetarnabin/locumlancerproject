<?php
// src/Controller/Provider/ToDoController.php

namespace App\Controller\Provider;

use App\Entity\ToDo;
use App\Repository\ToDoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/provider/todo')]
class ToDoController extends AbstractController
{
    #[Route('/', name: 'app_provider_todo')]
    public function index(ToDoRepository $todoRepository): Response
    {
        $provider = $this->getUser()->getProvider();
        $todos = $todoRepository->findPendingByProvider($provider);

        return $this->render('provider/todo/index.html.twig', [
            'todos' => $todos,
        ]);
    }

    #[Route('/{id}/complete', name: 'app_provider_todo_complete', methods: ['POST'])]
    public function complete(ToDo $todo, EntityManagerInterface $em, Request $request): Response
    {
        $provider = $this->getUser()->getProvider();

        // Security check - ensure the todo belongs to the current provider
        if ($todo->getProvider() !== $provider) {
            $this->addFlash('error', 'You do not have permission to complete this task.');
            return $this->redirectToRoute('app_provider_todo');
        }

        $todo->setIsCompleted(true);
        $em->flush();

        $this->addFlash('success', 'Task marked as completed.');
        return $this->redirectToRoute('app_provider_todo');
    }

    #[Route('/{id}/redirect', name: 'app_provider_todo_redirect', methods: ['GET'])]
    public function redirectToAction(ToDo $todo): Response
    {
        $provider = $this->getUser()->getProvider();

        // Security check
        if ($todo->getProvider() !== $provider) {
            $this->addFlash('error', 'You do not have permission to access this task.');
            return $this->redirectToRoute('app_provider_todo');
        }

        // Redirect based on todo type
        switch ($todo->getType()) {
            case 'document_request':
                return $this->redirectToRoute('app_provider_documents');
            // Add more cases for other todo types
            default:
                $this->addFlash('warning', 'No specific action defined for this task type.');
                return $this->redirectToRoute('app_provider_todo');
        }
    }
}
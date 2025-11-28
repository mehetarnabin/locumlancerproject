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
    public function index(): Response
    {
        return $this->render('provider/todo/index.html.twig');
    }

    #[Route('/pending.json', name: 'app_provider_todo_pending', methods: ['GET'])]
    public function pendingJson(ToDoRepository $todoRepository): Response
    {
        $provider = $this->getUser()->getProvider();
        $todos = $todoRepository->findPendingByProvider($provider);

        $data = array_map(function (ToDo $todo) {
            return $this->formatTodoData($todo);
        }, $todos);

        return $this->json([
            'success' => true, 
            'data' => $data,
            'count' => count($data)
        ]);
    }

    #[Route('/{id}/complete', name: 'app_provider_todo_complete', methods: ['POST'])]
    public function complete(ToDo $todo, EntityManagerInterface $em): Response
    {
        $provider = $this->getUser()->getProvider();

        if ($todo->getProvider() !== $provider) {
            return $this->json(['success' => false, 'error' => 'Permission denied'], 403);
        }

        $todo->setIsCompleted(true);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Task completed']);
    }

    private function formatTodoData(ToDo $todo): array
{
    $documentRequest = $todo->getDocumentRequest();
    $application = $documentRequest ? $documentRequest->getApplication() : null;
    $job = $application ? $application->getJob() : null;

    $baseData = [
        'id' => (string)$todo->getId(),
        'title' => $todo->getTitle(),
        'description' => $todo->getDescription(), // This shows the specific document name
        'type' => $todo->getType(),
        'createdAt' => $todo->getCreatedAt()->format('M j, g:i A'),
    ];

    // For document requests - show specific document information
    if ($todo->getType() === 'document_request') {
        $baseData['jobTitle'] = $job ? $job->getTitle() : 'Unknown Job';
        $baseData['employer'] = $todo->getEmployerName() ?: 'Unknown Employer'; // Use the helper method
        $baseData['actionUrl'] = $this->generateUrl('app_provider_documents');
        $baseData['actionText'] = 'Upload Document';
        $baseData['icon'] = '📄';
    }

    return $baseData;
}
}
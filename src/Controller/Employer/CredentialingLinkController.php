<?php

namespace App\Controller\Employer;

use App\Entity\Provider;
use App\Entity\Job;
use App\Entity\Notification;
use App\Service\NotificationService;
use App\Service\CredentialingLinkService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CredentialingLinkController extends AbstractController
{
    #[Route('/api/credentialing-links', name: 'create_credentialing_link', methods: ['POST'])]
    public function createCredentialingLink(
        Request $request,
        CredentialingLinkService $credentialingLinkService,
        EntityManagerInterface $entityManager, // Add EntityManager dependency
        NotificationService $notificationService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        $requiredFields = ['providerId', 'title', 'url'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return $this->json([
                    'success' => false,
                    'message' => "Missing required field: $field"
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            // Find provider using EntityManager
            $provider = $entityManager
                ->getRepository(Provider::class)
                ->find($data['providerId']);

            if (!$provider) {
                return $this->json([
                    'success' => false,
                    'message' => 'Provider not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Create the credentialing link
            $credentialingLink = $credentialingLinkService->createCredentialingLink(
                $provider,
                $data['title'],
                $data['url'],
                $data['description'] ?? null,
                $data['sender'] ?? null,
                isset($data['jobId']) ? $entityManager->getRepository(Job::class)->find($data['jobId']) : null
            );

            // Create ToDo for provider to view the external link
            $todo = new \App\Entity\ToDo();
            $todo->setProvider($provider);
            $todo->setEmployer($this->getUser()->getEmployer());
            $todo->setDocumentRequest(null);
            $todo->setTitle('External link: ' . ($credentialingLink->getTitle() ?: 'Credentialing'));
            $todo->setDescription($credentialingLink->getUrl());
            $todo->setType('credentialing_link');
            $entityManager->persist($todo);
            $entityManager->flush();

            // Notify provider about the credentialing link
            $providerUser = $provider->getUser();
            if ($providerUser) {
                $employerName = $this->getUser()->getEmployer()?->getName() ?? 'Employer';
                $message = $employerName . ' sent a credentialing link: ' . ($credentialingLink->getTitle() ?: 'Credentialing');
                $notificationService->sendNotification(
                    $providerUser,
                    Notification::DOCUMENT_REQUESTED,
                    $message,
                    true,
                    [
                        'provider' => $provider->getId(),
                        'employer' => $this->getUser()->getEmployer()->getId(),
                        'linkTitle' => $credentialingLink->getTitle(),
                        'linkUrl' => $credentialingLink->getUrl(),
                    ]
                );
            }

            return $this->json([
                'success' => true,
                'message' => 'Credentialing link created successfully',
                'data' => [
                    'id' => $credentialingLink->getId(),
                    'title' => $credentialingLink->getTitle(),
                    'url' => $credentialingLink->getUrl(),
                    'description' => $credentialingLink->getDescription(),
                    'createdAt' => $credentialingLink->getCreatedAt()->format('Y-m-d H:i:s'),
                    'jobId' => $credentialingLink->getJob() ? (string)$credentialingLink->getJob()->getId() : null,
                    'jobTitle' => $credentialingLink->getJob() ? $credentialingLink->getJob()->getTitle() : null
                ]
            ], Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'An error occurred while creating the credentialing link: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/providers/{id}/credentialing-links', name: 'get_provider_credentialing_links', methods: ['GET'])]
    public function getProviderCredentialingLinks(
        Provider $provider,
        CredentialingLinkService $credentialingLinkService
    ): JsonResponse {
        $links = $credentialingLinkService->getActiveLinksForProvider($provider);

        $linksData = array_map(function ($link) {
            return [
                'id' => $link->getId(),
                'title' => $link->getTitle(),
                'url' => $link->getUrl(),
                'description' => $link->getDescription(),
                'sender' => $link->getSender(),
                'createdAt' => $link->getCreatedAt()->format('Y-m-d H:i:s')
            ];
        }, $links);

        return $this->json([
            'success' => true,
            'data' => $linksData
        ]);
    }
}

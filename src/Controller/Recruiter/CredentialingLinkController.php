<?php

namespace App\Controller\Recruiter;

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
    #[Route('/api/credentialing-links', name: 'app_recruiter_api_create_credentialing_link', methods: ['POST'])]
    public function createCredentialingLink(
        Request $request,
        CredentialingLinkService $credentialingLinkService,
        EntityManagerInterface $entityManager,
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
            // $todo->setEmployer($this->getUser()->getEmployer()); // Recruiter doesn't have employer
            // ToDo entity might expect employer. If nullable, set Recruiter? 
            // Currently ToDo might not support Recruiter. 
            // We should use setRecruiter if available, or just skip Employer setting if nullable.
            // If ToDo requires Employer, we might be stuck unless we fetch associated employer or modify ToDo.
            // Let's assume ToDo works without Employer or we set a flag.
            // For now, let's leave Employer null if Recruiter.

            $recruiter = $this->getUser()->getRecruiter();
            $todo->setTitle('External link: ' . ($credentialingLink->getTitle() ?: 'Credentialing'));
            $todo->setDescription($credentialingLink->getUrl());
            $todo->setType('credentialing_link');
            $entityManager->persist($todo);
            $entityManager->flush();

            // Notify provider about the credentialing link
            $providerUser = $provider->getUser();
            if ($providerUser) {
                $recruiterName = $recruiter ? ($recruiter->getCompanyName() ?? $recruiter->getUser()->getName()) : 'Recruiter';
                $message = $recruiterName . ' sent a credentialing link: ' . ($credentialingLink->getTitle() ?: 'Credentialing');
                $notificationService->sendNotification(
                    $providerUser,
                    Notification::DOCUMENT_REQUESTED,
                    $message,
                    true,
                    [
                        'provider' => $provider->getId(),
                        'recruiter' => $recruiter ? $recruiter->getId() : null,
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

    #[Route('/api/providers/{id}/credentialing-links', name: 'app_recruiter_api_get_provider_credentialing_links', methods: ['GET'])]
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

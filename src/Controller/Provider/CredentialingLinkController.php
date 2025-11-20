<?php

namespace App\Controller\Provider;

use App\Entity\CredentialingLink;
use App\Entity\Provider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CredentialingLinkController extends AbstractController
{
    /**
     * @Route("/api/provider/links", name="api_provider_links_create", methods={"POST"})
     */
    public function receiveLink(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Basic validation
        if (!isset($data['provider_id']) || !isset($data['url']) || !isset($data['title'])) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        $provider = $this->getDoctrine()->getRepository(Provider::class)->find($data['provider_id']);
        if (!$provider) {
            return $this->json(['error' => 'Provider not found'], 404);
        }

        // Create the link
        $link = new CredentialingLink();
        $link->setProvider($provider);
        $link->setTitle($data['title']);
        $link->setUrl($data['url']);
        $link->setDescription($data['description'] ?? null);
        $link->setSender($data['sender'] ?? 'System');
        $link->setCreatedAt(new \DateTime());
        $link->setIsActive(true);

        $em = $this->getDoctrine()->getManager();
        $em->persist($link);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Link delivered successfully',
            'link_id' => $link->getId()
        ]);
    }
}
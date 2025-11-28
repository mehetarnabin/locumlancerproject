<?php

namespace App\Service;

use App\Entity\CredentialingLink;
use App\Entity\Job;
use App\Entity\Provider;
use Doctrine\ORM\EntityManagerInterface;

class CredentialingLinkService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Create a new credentialing link from external platform
     */
    public function createCredentialingLink(
        Provider $provider,
        string $title,
        string $url,
        ?string $description = null,
        ?string $sender = null,
        ?Job $job = null
    ): CredentialingLink {
        // Validate URL
        if (!$this->isValidUrl($url)) {
            throw new \InvalidArgumentException('Invalid URL provided');
        }

        // Create new credentialing link
        $credentialingLink = new CredentialingLink();
        $credentialingLink->setProvider($provider);
        $credentialingLink->setTitle($title);
        $credentialingLink->setUrl($url);
        $credentialingLink->setDescription($description);
        $credentialingLink->setSender($sender);
        $credentialingLink->setCreatedAt(new \DateTime());
        $credentialingLink->setIsActive(true);
        $credentialingLink->setJob($job);

        // Persist to database
        $this->entityManager->persist($credentialingLink);
        $this->entityManager->flush();

        return $credentialingLink;
    }

    /**
     * Validate URL format
     */
    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Get active credentialing links for a provider
     */
    public function getActiveLinksForProvider(Provider $provider): array
    {
        return $this->entityManager
            ->getRepository(CredentialingLink::class)
            ->findBy([
                'provider' => $provider,
                'isActive' => true
            ], ['createdAt' => 'DESC']);
    }

    /**
     * Deactivate a credentialing link
     */
    public function deactivateLink(CredentialingLink $link): void
    {
        $link->setIsActive(false);
        $this->entityManager->flush();
    }
}

<?php
// src/Entity/ProfileAnalytics.php

namespace App\Entity;

use App\Repository\ProfileAnalyticsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfileAnalyticsRepository::class)]
#[ORM\Table(name: 'profile_analytics')]
class ProfileAnalytics
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Provider::class)]
    #[ORM\JoinColumn(name: 'viewed_provider_id', referencedColumnName: 'id', nullable: false)]
    private ?Provider $viewedProvider = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'viewer_id', referencedColumnName: 'id', nullable: true)]
    private ?User $viewer = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getViewedProvider(): ?Provider { return $this->viewedProvider; }
    public function setViewedProvider(?Provider $viewedProvider): self { $this->viewedProvider = $viewedProvider; return $this; }
    public function getViewer(): ?User { return $this->viewer; }
    public function setViewer(?User $viewer): self { $this->viewer = $viewer; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ipAddress): self { $this->ipAddress = $ipAddress; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $userAgent): self { $this->userAgent = $userAgent; return $this; }
}
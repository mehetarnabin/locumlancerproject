<?php
// src/Entity/ToDo.php

namespace App\Entity;

use App\Repository\ToDoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ToDoRepository::class)]
class ToDo
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Provider::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Provider $provider;

    #[ORM\ManyToOne(targetEntity: Employer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Employer $employer;

    #[ORM\ManyToOne(targetEntity: DocumentRequest::class)]
    #[ORM\JoinColumn(nullable: false)]
    private DocumentRequest $documentRequest;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private string $type = 'document_request';

    #[ORM\Column]
    private bool $isCompleted = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v6();
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters and setters
    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProvider(): Provider
    {
        return $this->provider;
    }

    public function setProvider(Provider $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function getEmployer(): Employer
    {
        return $this->employer;
    }

    public function setEmployer(Employer $employer): static
    {
        $this->employer = $employer;
        return $this;
    }

    public function getDocumentRequest(): DocumentRequest
    {
        return $this->documentRequest;
    }

    public function setDocumentRequest(DocumentRequest $documentRequest): static
    {
        $this->documentRequest = $documentRequest;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->isCompleted;
    }

    public function setIsCompleted(bool $isCompleted): static
    {
        $this->isCompleted = $isCompleted;
        $this->completedAt = $isCompleted ? new \DateTimeImmutable() : null;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }
}
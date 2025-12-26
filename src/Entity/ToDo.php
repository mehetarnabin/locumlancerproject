<?php
// src/Entity/ToDo.php

namespace App\Entity;

use App\Repository\ToDoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ToDoRepository::class)]
#[ORM\Table(name: 'to_do')]
class ToDo
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Provider::class)]
    #[ORM\JoinColumn(name: 'provider_id', nullable: false)]
    private ?Provider $provider = null;

    #[ORM\ManyToOne(targetEntity: Employer::class)]
    #[ORM\JoinColumn(name: 'employer_id', nullable: true)]
    private ?Employer $employer = null;

    #[ORM\ManyToOne(targetEntity: Recruiter::class)]
    #[ORM\JoinColumn(name: 'recruiter_id', nullable: true)]
    private ?Recruiter $recruiter = null;

    #[ORM\ManyToOne(targetEntity: Bookmark::class)]
    #[ORM\JoinColumn(name: 'bookmark_id', nullable: true)]
    private ?Bookmark $bookmark = null;

    #[ORM\ManyToOne(targetEntity: Job::class)]
    #[ORM\JoinColumn(name: 'job_id', nullable: true)]
    private ?Job $job = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\ManyToOne(targetEntity: DocumentRequest::class)]
    #[ORM\JoinColumn(name: 'document_request_id', nullable: true)]
    private ?DocumentRequest $documentRequest = null;

    #[ORM\Column(name: 'is_completed', type: 'boolean')]
    private bool $isCompleted = false;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt;

    #[ORM\Column(name: 'completed_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->isCompleted = false;
    }

    // Getters and Setters
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getProvider(): ?Provider
    {
        return $this->provider;
    }

    public function setProvider(?Provider $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function getEmployer(): ?Employer
    {
        return $this->employer;
    }

    public function setEmployer(?Employer $employer): static
    {
        $this->employer = $employer;
        return $this;
    }

    public function getRecruiter(): ?Recruiter
    {
        return $this->recruiter;
    }

    public function setRecruiter(?Recruiter $recruiter): static
    {
        $this->recruiter = $recruiter;
        return $this;
    }

    public function getEmployerName(): ?string
    {
        return $this->employer ? ($this->employer->getCompanyName() ?: $this->employer->getName()) : null;
    }

    public function getBookmark(): ?Bookmark
    {
        return $this->bookmark;
    }

    public function setBookmark(?Bookmark $bookmark): static
    {
        $this->bookmark = $bookmark;
        return $this;
    }

    public function getJob(): ?Job
    {
        return $this->job;
    }

    public function setJob(?Job $job): static
    {
        $this->job = $job;
        return $this;
    }

    // Compatibility methods for JobController
    public function getText(): ?string
    {
        return $this->title;
    }

    public function setText(string $text): static
    {
        $this->title = $text;
        return $this;
    }

    public function isDone(): bool
    {
        return $this->isCompleted;
    }

    public function setDone(bool $done): static
    {
        $this->setIsCompleted($done);
        return $this;
    }

    public function getTitle(): ?string
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getDocumentRequest(): ?DocumentRequest
    {
        return $this->documentRequest;
    }

    public function setDocumentRequest(?DocumentRequest $documentRequest): static
    {
        $this->documentRequest = $documentRequest;
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->isCompleted;
    }

    public function setIsCompleted(bool $isCompleted): static
    {
        $this->isCompleted = $isCompleted;
        $this->completedAt = $isCompleted ? new \DateTime() : null;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }
}

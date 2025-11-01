<?php

namespace App\Entity;

use App\Repository\WorkflowLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'b_workflow_log')]
#[ORM\Entity(repositoryClass: WorkflowLogRepository::class)]
class WorkflowLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private ?string $subjectClass = null;

    #[ORM\Column(length: 255)]
    private ?string $subjectId = null;

    #[ORM\Column(length: 255)]
    private ?string $transition = null;

    #[ORM\Column(length: 255)]
    private ?string $fromState = null;

    #[ORM\Column(length: 255)]
    private ?string $toState = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $transitionedAt = null;

    #[ORM\ManyToOne]
    private ?User $transitionedBy = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getSubjectClass(): ?string
    {
        return $this->subjectClass;
    }

    public function setSubjectClass(string $subjectClass): static
    {
        $this->subjectClass = $subjectClass;

        return $this;
    }

    public function getSubjectId(): ?string
    {
        return $this->subjectId;
    }

    public function setSubjectId(string $subjectId): static
    {
        $this->subjectId = $subjectId;

        return $this;
    }

    public function getTransition(): ?string
    {
        return $this->transition;
    }

    public function setTransition(string $transition): static
    {
        $this->transition = $transition;

        return $this;
    }

    public function getFromState(): ?string
    {
        return $this->fromState;
    }

    public function setFromState(string $fromState): static
    {
        $this->fromState = $fromState;

        return $this;
    }

    public function getToState(): ?string
    {
        return $this->toState;
    }

    public function setToState(string $toState): static
    {
        $this->toState = $toState;

        return $this;
    }

    public function getTransitionedAt(): ?\DateTimeInterface
    {
        return $this->transitionedAt;
    }

    public function setTransitionedAt(\DateTimeInterface $transitionedAt): static
    {
        $this->transitionedAt = $transitionedAt;

        return $this;
    }

    public function getTransitionedBy(): ?User
    {
        return $this->transitionedBy;
    }

    public function setTransitionedBy(?User $transitionedBy): void
    {
        $this->transitionedBy = $transitionedBy;
    }
}

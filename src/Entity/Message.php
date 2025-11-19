<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'b_message')]
#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    private ?Message $parent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $sender = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $receiver = null;

    #[ORM\ManyToOne]
    private ?Employer $employer = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $text = null;

    #[ORM\Column]
    private ?bool $seen = false;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $attachment = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $subject = null;

    // Draft functionality fields
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $isDraft = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $savedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $sentAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $deleted = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->isDraft = false;
        $this->seen = false;
        $this->deleted = false;
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getParent(): ?Message
    {
        return $this->parent;
    }

    public function setParent(?Message $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): static
    {
        $this->sender = $sender;
        return $this;
    }

    public function getReceiver(): ?User
    {
        return $this->receiver;
    }

    public function setReceiver(?User $receiver): static
    {
        $this->receiver = $receiver;
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

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function isSeen(): ?bool
    {
        return $this->seen;
    }

    public function setSeen(bool $seen): static
    {
        $this->seen = $seen;
        return $this;
    }

    public function getAttachment(): ?string
    {
        return $this->attachment;
    }

    public function setAttachment(?string $attachment): static
    {
        $this->attachment = $attachment;
        return $this;
    }

    public function isDraft(): bool
    {
        return $this->isDraft;
    }

    public function setIsDraft(bool $isDraft): static
    {
        $this->isDraft = $isDraft;
        
        if ($isDraft) {
            $this->savedAt = new \DateTime();
            $this->sentAt = null;
            $this->seen = true; // Drafts are always "seen" by sender
        } else {
            $this->sentAt = $this->sentAt ?: new \DateTime();
            $this->seen = false;
        }
        
        return $this;
    }

    public function getSavedAt(): ?\DateTimeInterface
    {
        return $this->savedAt;
    }

    public function setSavedAt(?\DateTimeInterface $savedAt): static
    {
        $this->savedAt = $savedAt;
        return $this;
    }

    public function getSentAt(): ?\DateTimeInterface
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeInterface $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    // Helper method to send a draft
    public function send(): static
    {
        $this->setIsDraft(false);
        return $this;
    }

    // Helper method to check if message can be sent (has receiver and text)
    public function canBeSent(): bool
    {
        return $this->getReceiver() !== null && !empty(trim($this->getText()));
    }

    public function isDeleted(): ?bool
    {
        return $this->deleted;
    }

    public function setDeleted(?bool $deleted): static
    {
        $this->deleted = $deleted;
        if ($deleted) {
            $this->deletedAt = new \DateTime();
        } else {
            $this->deletedAt = null;
        }
        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): static
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }
}
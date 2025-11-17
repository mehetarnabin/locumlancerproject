<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\ManyToOne(targetEntity: Message::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'CASCADE', nullable: true)]
    private ?Message $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: Message::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $children;

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

    #[ORM\Column(type: Types::STRING, length: 20)]
    private ?string $type = 'inbox'; // 'inbox', 'sent', 'draft'

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->type = 'inbox';
        $this->seen = false;
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

    /**
     * @return Collection<int, Message>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(Message $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(Message $child): static
    {
        if ($this->children->removeElement($child)) {
            // set the owning side to null (unless already changed)
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function markAsSent(): static
    {
        $this->type = 'sent';
        $this->sentAt = new \DateTimeImmutable();
        return $this;
    }

    public function isInbox(): bool
    {
        return $this->type === 'inbox';
    }

    public function isSent(): bool
    {
        return $this->type === 'sent';
    }

    public function isDraft(): bool
    {
        return $this->type === 'draft';
    }

    public function markAsRead(): static
    {
        $this->seen = true;
        return $this;
    }

    public function markAsUnread(): static
    {
        $this->seen = false;
        return $this;
    }
}
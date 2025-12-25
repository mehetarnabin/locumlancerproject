<?php
// src/Entity/PackageSubscription.php

namespace App\Entity;

use App\Repository\PackageSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'b_package_subscription')]
#[ORM\Entity(repositoryClass: PackageSubscriptionRepository::class)]
#[ORM\Index(name: 'idx_user_status', columns: ['user_id', 'status'])]
#[ORM\Index(name: 'idx_end_date', columns: ['end_date'])]
class PackageSubscription
{
    use TimestampableEntity;

    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_PENDING = 'pending';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Package::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Package $package = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $paidAmount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transactionId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeSubscriptionId = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $usedJobPosts = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $usedApplications = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $remainingJobPosts = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $remainingApplications = 0;

    // Getters and Setters
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getPackage(): ?Package
    {
        return $this->package;
    }

    public function setPackage(?Package $package): static
    {
        $this->package = $package;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getPaidAmount(): ?string
    {
        return $this->paidAmount;
    }

    public function setPaidAmount(string $paidAmount): static
    {
        $this->paidAmount = $paidAmount;
        return $this;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): static
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function setStripeSubscriptionId(?string $stripeSubscriptionId): static
    {
        $this->stripeSubscriptionId = $stripeSubscriptionId;
        return $this;
    }

    public function getUsedJobPosts(): int
    {
        return $this->usedJobPosts;
    }

    public function setUsedJobPosts(int $usedJobPosts): static
    {
        $this->usedJobPosts = $usedJobPosts;
        return $this;
    }

    public function getUsedApplications(): int
    {
        return $this->usedApplications;
    }

    public function setUsedApplications(int $usedApplications): static
    {
        $this->usedApplications = $usedApplications;
        return $this;
    }

    public function getRemainingJobPosts(): int
    {
        return $this->remainingJobPosts;
    }

    public function setRemainingJobPosts(int $remainingJobPosts): static
    {
        $this->remainingJobPosts = $remainingJobPosts;
        return $this;
    }

    public function getRemainingApplications(): int
    {
        return $this->remainingApplications;
    }

    public function setRemainingApplications(int $remainingApplications): static
    {
        $this->remainingApplications = $remainingApplications;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->endDate > new \DateTime();
    }

    public function incrementUsedJobPosts(): void
    {
        $this->usedJobPosts++;
        $this->remainingJobPosts = max(0, ($this->package->getMaxJobPosts() ?? 0) - $this->usedJobPosts);
    }

    public function incrementUsedApplications(): void
    {
        $this->usedApplications++;
        $this->remainingApplications = max(0, ($this->package->getMaxApplications() ?? 0) - $this->usedApplications);
    }
}

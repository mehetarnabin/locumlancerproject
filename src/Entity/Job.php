<?php

namespace App\Entity;

use App\Repository\JobRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'b_job')]
#[ORM\Entity(repositoryClass: JobRepository::class)]
class Job
{
    use TimestampableEntity;

    public const JOB_STATUS_DRAFT = 'draft';
    public const JOB_STATUS_PUBLISHED = 'published';
    public const JOB_STATUS_PAUSED = 'paused';
    public const JOB_STATUS_CLOSED = 'closed';

    // -------------------- Primary Key --------------------
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    // -------------------- Relations --------------------
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Employer::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Employer $employer = null;

    #[ORM\ManyToOne(targetEntity: Profession::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Profession $profession = null;

    #[ORM\ManyToOne(targetEntity: Speciality::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Speciality $speciality = null;

    // -------------------- Fields --------------------
    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(length: 40)]
    private ?string $status = self::JOB_STATUS_DRAFT;

    #[ORM\Column(length: 255)]
    private ?string $country = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $state = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $streetAddress = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expirationDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $highlight = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $schedule = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $yearOfExperience = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $payRate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $workType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $need = null;

    #[ORM\Column(nullable: true)]
    private ?int $payRateHourly = null;

    #[ORM\Column(nullable: true)]
    private ?int $payRateDaily = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $blocked = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $jobId = null;

    #[ORM\Column(nullable: true)]
    private ?bool $verified = null;

    // Temporarily removed from database mapping to avoid "column not found" error
    // This property exists but is NOT mapped to the database - no ORM annotation
    private bool $archived = false;
    

    // -------------------- Getters & Setters --------------------

    public function __toString(): string
    {
        return (string) $this->title;
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

    public function getEmployer(): ?Employer
    {
        return $this->employer;
    }

    public function setEmployer(?Employer $employer): static
    {
        $this->employer = $employer;
        return $this;
    }

    public function getProfession(): ?Profession
    {
        return $this->profession;
    }

    public function setProfession(?Profession $profession): void
    {
        $this->profession = $profession;
    }

    public function getSpeciality(): ?Speciality
    {
        return $this->speciality;
    }

    public function setSpeciality(?Speciality $speciality): void
    {
        $this->speciality = $speciality;
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

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;
        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): static
    {
        $this->state = $state;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;
        return $this;
    }

    public function getStreetAddress(): ?string
    {
        return $this->streetAddress;
    }

    public function setStreetAddress(?string $streetAddress): static
    {
        $this->streetAddress = $streetAddress;
        return $this;
    }

    public function getExpirationDate(): ?\DateTimeInterface
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(?\DateTimeInterface $expirationDate): static
    {
        $this->expirationDate = $expirationDate;
        return $this;
    }

    public function getHighlight(): ?string
    {
        return $this->highlight;
    }

    public function setHighlight(?string $highlight): static
    {
        $this->highlight = $highlight;
        return $this;
    }

    public function getSchedule(): ?string
    {
        return $this->schedule;
    }

    public function setSchedule(?string $schedule): static
    {
        $this->schedule = $schedule;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getYearOfExperience(): ?int
    {
        return $this->yearOfExperience;
    }

    public function setYearOfExperience(?int $yearOfExperience): static
    {
        $this->yearOfExperience = $yearOfExperience;
        return $this;
    }

    public function getPayRate(): ?string
    {
        return $this->payRate;
    }

    public function setPayRate(?string $payRate): void
    {
        $this->payRate = $payRate;
    }

    public function getWorkType(): ?string
    {
        return $this->workType;
    }

    public function setWorkType(?string $workType): void
    {
        $this->workType = $workType;
    }

    public function getNeed(): ?string
    {
        return $this->need;
    }

    public function setNeed(?string $need): void
    {
        $this->need = $need;
    }

    public function getPayRateHourly(): ?int
    {
        return $this->payRateHourly;
    }

    public function setPayRateHourly(?int $payRateHourly): void
    {
        $this->payRateHourly = $payRateHourly;
    }

    public function getPayRateDaily(): ?int
    {
        return $this->payRateDaily;
    }

    public function setPayRateDaily(?int $payRateDaily): void
    {
        $this->payRateDaily = $payRateDaily;
    }

    public function isBlocked(): ?bool
    {
        return $this->blocked;
    }

    public function setBlocked(?bool $blocked): void
    {
        $this->blocked = $blocked;
    }

    public function getJobId(): ?string
    {
        return $this->jobId;
    }

    public function setJobId(?string $jobId): void
    {
        $this->jobId = $jobId;
    }

    public function isVerified(): ?bool
    {
        return $this->verified;
    }

    public function setVerified(?bool $verified): static
    {
        $this->verified = $verified;
        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): self
    {
        $this->archived = $archived;
        return $this;
    }

    public function getArchived(): bool
    {
        return $this->archived;
    }
}

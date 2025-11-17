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
    const JOB_STATUS_DRAFT = 'draft';
    const JOB_STATUS_PUBLISHED = 'published';
    const JOB_STATUS_PAUSED = 'paused';
    const JOB_STATUS_CLOSED = 'closed';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    private ?User $user = null;

    #[ORM\ManyToOne]
    private ?Employer $employer = null;

    #[ORM\ManyToOne]
    private ?Profession $profession = null;

    #[ORM\ManyToOne]
    private ?Speciality $speciality = null;

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

    #[ORM\Column(nullable: true)]
    private ?int $annualSalary = null;

    #[ORM\Column(nullable: true)]
    private ?int $monthlySalary = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $salaryType = null; // hourly, daily, monthly, annual

    #[ORM\Column(type: 'boolean', nullable: true)]
    private $blocked = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $jobId = null;

    #[ORM\Column(nullable: true)]
    private ?bool $verified = null;


    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $rank = null; // 1-5 stars


    use TimestampableEntity;

    public function __toString()
    {
        return $this->title;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

        public function getRank(): ?int
    {
        return $this->rank;
    }

    public function setRank(?int $rank): self
    {
        $this->rank = $rank;
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

    public function setHighlight(string $highlight): static
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

        public function getAnnualSalary(): ?int
    {
        return $this->annualSalary;
    }

    public function setAnnualSalary(?int $annualSalary): void
    {
        $this->annualSalary = $annualSalary;
    }

    public function getMonthlySalary(): ?int
    {
        return $this->monthlySalary;
    }

    public function setMonthlySalary(?int $monthlySalary): void
    {
        $this->monthlySalary = $monthlySalary;
    }

    public function getSalaryType(): ?string
    {
        return $this->salaryType;
    }

    public function setSalaryType(?string $salaryType): void
    {
        $this->salaryType = $salaryType;
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

    public function setPayRate(?string $payRate): void
    {
        $this->payRate = $payRate;
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



public function getFormattedSalary(): string
{
    // Check by salaryType first
    if ($this->salaryType === 'annual' && $this->annualSalary) {
        return '$' . number_format($this->annualSalary) . '/year';
    }
    
    if ($this->salaryType === 'monthly' && $this->monthlySalary) {
        return '$' . number_format($this->monthlySalary) . '/month';
    }
    
    if ($this->salaryType === 'daily' && $this->payRateDaily) {
        return '$' . number_format($this->payRateDaily) . '/day';
    }
    
    if ($this->salaryType === 'hourly' && $this->payRateHourly) {
        return '$' . number_format($this->payRateHourly, 2) . '/hour';
    }
    
    // Fallback: Check individual salary fields if salaryType is not set
    if ($this->annualSalary) {
        return '$' . number_format($this->annualSalary) . '/year';
    }
    
    if ($this->monthlySalary) {
        return '$' . number_format($this->monthlySalary) . '/month';
    }
    
    if ($this->payRateDaily) {
        return '$' . number_format($this->payRateDaily) . '/day';
    }
    
    if ($this->payRateHourly) {
        return '$' . number_format($this->payRateHourly, 2) . '/hour';
    }
    
    // Check the old payRate field for backward compatibility
    if ($this->payRate) {
        return $this->payRate; // This might already be formatted
    }
    
    return 'Salary not specified';
}

public function getSalaryForSorting(): ?int
{
    // Try salaryType first
    $value = match($this->salaryType) {
        'annual' => $this->annualSalary,
        'monthly' => $this->monthlySalary,
        'daily' => $this->payRateDaily,
        'hourly' => $this->payRateHourly,
        default => null
    };
    
    // Fallback to any available salary value
    if ($value === null) {
        $value = $this->annualSalary ?? $this->monthlySalary ?? $this->payRateDaily ?? $this->payRateHourly;
    }
    
    return $value;
}

/**
 * Get hourly rate, converting from any salary type to hourly
 * Standard conversions:
 * - Annual: divide by 2080 hours/year (40 hours/week * 52 weeks)
 * - Monthly: divide by 173.33 hours/month (2080/12)
 * - Daily: divide by 8 hours/day
 * - Hourly: return as-is
 */
public function getHourlyRate(): ?float
{
    // Check by salaryType first
    if ($this->salaryType === 'hourly' && $this->payRateHourly) {
        return (float)$this->payRateHourly;
    }
    
    if ($this->salaryType === 'annual' && $this->annualSalary) {
        // Convert annual to hourly: divide by 2080 hours/year
        return round((float)$this->annualSalary / 2080, 2);
    }
    
    if ($this->salaryType === 'monthly' && $this->monthlySalary) {
        // Convert monthly to hourly: divide by 173.33 hours/month
        return round((float)$this->monthlySalary / 173.33, 2);
    }
    
    if ($this->salaryType === 'daily' && $this->payRateDaily) {
        // Convert daily to hourly: divide by 8 hours/day
        return round((float)$this->payRateDaily / 8, 2);
    }
    
    // Fallback: Check individual salary fields if salaryType is not set
    if ($this->payRateHourly) {
        return (float)$this->payRateHourly;
    }
    
    if ($this->annualSalary) {
        return round((float)$this->annualSalary / 2080, 2);
    }
    
    if ($this->monthlySalary) {
        return round((float)$this->monthlySalary / 173.33, 2);
    }
    
    if ($this->payRateDaily) {
        return round((float)$this->payRateDaily / 8, 2);
    }
    
    return null;
}

/**
 * Get formatted hourly rate as string (e.g., "$50.00/hour")
 */
public function getFormattedHourlyRate(): string
{
    $hourlyRate = $this->getHourlyRate();
    if ($hourlyRate === null) {
        return '—';
    }
    return '$' . number_format($hourlyRate, 2) . '/hour';
}
}

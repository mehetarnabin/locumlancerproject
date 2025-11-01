<?php

namespace App\Entity;

use App\Repository\ExperienceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'b_provider_experience')]
#[ORM\Entity(repositoryClass: ExperienceRepository::class)]
class Experience
{
    const EMPLOYMENT_TYPE_FULL_TIME = 'Full-time';
    const EMPLOYMENT_TYPE_PART_TIME = 'Part-time';
    const EMPLOYMENT_TYPE_SELF_EMPLOYED = 'Self-employed';
    const EMPLOYMENT_TYPE_FREELANCE = 'Freelance';
    const EMPLOYMENT_TYPE_CONTRACT = 'Contract';
    const EMPLOYMENT_TYPE_INTERNSHIP = 'Internship';
    const EMPLOYMENT_TYPE_APPRENTICESHIP = 'Apprenticeship';
    const EMPLOYMENT_TYPE_SEASONAL = 'Seasonal';

    public static $employmentTypes = [
        self::EMPLOYMENT_TYPE_FULL_TIME => 'Full-time',
        self::EMPLOYMENT_TYPE_PART_TIME => 'Part-time',
        self::EMPLOYMENT_TYPE_SELF_EMPLOYED => 'Self-employed',
        self::EMPLOYMENT_TYPE_FREELANCE => 'Freelance',
        self::EMPLOYMENT_TYPE_CONTRACT => 'Contract',
        self::EMPLOYMENT_TYPE_INTERNSHIP => 'Internship',
        self::EMPLOYMENT_TYPE_APPRENTICESHIP => 'Apprenticeship',
        self::EMPLOYMENT_TYPE_SEASONAL => 'Seasonal',
    ];

    const LOCATION_TYPE_ON_SITE = 'On-site';
    const LOCATION_TYPE_HYBRID = 'Hybrid';
    const LOCATION_TYPE_REMOTE = 'Remote';

    public static $locationTypes = [
        self::LOCATION_TYPE_ON_SITE => 'On-site',
        self::LOCATION_TYPE_HYBRID => 'Hybrid',
        self::LOCATION_TYPE_REMOTE => 'Remote',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 40)]
    private ?string $employmentType = null;

    #[ORM\Column(length: 255)]
    private ?string $company = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $locationType = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function __toString()
    {
        return $this->title;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
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

    public function getEmploymentType(): ?string
    {
        return $this->employmentType;
    }

    public function setEmploymentType(string $employmentType): static
    {
        $this->employmentType = $employmentType;

        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(string $company): static
    {
        $this->company = $company;

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

    public function setEndDate(?\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getLocationType(): ?string
    {
        return $this->locationType;
    }

    public function setLocationType(?string $locationType): static
    {
        $this->locationType = $locationType;

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
}

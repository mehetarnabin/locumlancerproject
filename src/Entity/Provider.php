<?php

namespace App\Entity;

use App\Repository\ProviderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'b_provider')]
#[ORM\Entity(repositoryClass: ProviderRepository::class)]
class Provider
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\OneToOne(inversedBy: 'provider', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $suffix = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $npiNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phoneHome = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phoneWork = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phoneMobile = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $cvFilename;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $state = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $zipCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $streetAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $desiredPayRate = null;

    #[ORM\Column(nullable: true)]
    private ?int $payRateHourly = null;

    #[ORM\Column(nullable: true)]
    private ?int $payRateDaily = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $desiredHour = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?array $desiredStates = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $availabilityToStart = null;

    #[ORM\Column]
    private ?bool $willingToTravel = false;

    #[ORM\Column(nullable: true)]
    private ?int $preferredPatientVolume = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $releaseAuthorizationDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ssn = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dob = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $countryOfBirth = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stateOfBirth = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cityOfBirth = null;

    #[ORM\Column(nullable: true)]
    private ?bool $releaseAndAuthorizationAccepted = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $applicationCertificationDate = null;

    #[ORM\Column(type: Types::ARRAY, nullable: true)]
    private ?array $riskAssessment = null;

    #[ORM\Column(type: Types::ARRAY, nullable: true)]
    private ?array $healthAssessment = null;

    #[ORM\ManyToOne]
    private ?Profession $profession = null;

    /**
     * @var Collection<int, Speciality>
     */
    #[ORM\ManyToMany(targetEntity: Speciality::class)]
    private Collection $specialities;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private $cashback = [];

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private $notificationPreferences = [];

    #[ORM\Column(nullable: true)]
    private ?float $averagePoint = null;

    #[ORM\Column(nullable: true)]
    private ?bool $skip_onboarding = null;

    #[ORM\OneToMany(targetEntity: Application::class,  mappedBy: 'provider')]
    private Collection $applications;

    public function __construct()
    {
        $this->specialities = new ArrayCollection();
    }

    use TimestampableEntity;

    public function __toString()
    {
        return (string) $this->name;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSuffix(): ?string
    {
        return $this->suffix;
    }

    public function setSuffix(?string $suffix): void
    {
        $this->suffix = $suffix;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getNpiNumber(): ?string
    {
        return $this->npiNumber;
    }

    public function setNpiNumber(?string $npiNumber): void
    {
        $this->npiNumber = $npiNumber;
    }

    public function getPhoneHome(): ?string
    {
        return $this->phoneHome;
    }

    public function setPhoneHome(?string $phoneHome): void
    {
        $this->phoneHome = $phoneHome;
    }

    public function getPhoneWork(): ?string
    {
        return $this->phoneWork;
    }

    public function setPhoneWork(?string $phoneWork): void
    {
        $this->phoneWork = $phoneWork;
    }

    public function getPhoneMobile(): ?string
    {
        return $this->phoneMobile;
    }

    public function setPhoneMobile(?string $phoneMobile): void
    {
        $this->phoneMobile = $phoneMobile;
    }

    public function getCvFilename(): ?string
    {
        return $this->cvFilename;
    }

    public function setCvFilename(?string $cvFilename): void
    {
        $this->cvFilename = $cvFilename;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): void
    {
        $this->country = $country;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): void
    {
        $this->state = $state;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }

    public function getZipCode(): ?string
    {
        return $this->zipCode;
    }

    public function setZipCode(?string $zipCode): void
    {
        $this->zipCode = $zipCode;
    }

    public function getStreetAddress(): ?string
    {
        return $this->streetAddress;
    }

    public function getDesiredPayRate(): ?string
    {
        return $this->desiredPayRate;
    }

    public function setDesiredPayRate(?string $desiredPayRate): void
    {
        $this->desiredPayRate = $desiredPayRate;
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

    public function getDesiredHour(): ?string
    {
        return $this->desiredHour;
    }

    public function setDesiredHour(?string $desiredHour): void
    {
        $this->desiredHour = $desiredHour;
    }

    public function getDesiredStates(): ?array
    {
        return $this->desiredStates;
    }

    public function setDesiredStates(?array $desiredStates): void
    {
        $this->desiredStates = $desiredStates;
    }

    public function setStreetAddress(?string $streetAddress): void
    {
        $this->streetAddress = $streetAddress;
    }

    public function getAvailabilityToStart(): ?\DateTimeInterface
    {
        return $this->availabilityToStart;
    }

    public function setAvailabilityToStart(?\DateTimeInterface $availabilityToStart): static
    {
        $this->availabilityToStart = $availabilityToStart;

        return $this;
    }

    public function isWillingToTravel(): ?bool
    {
        return $this->willingToTravel;
    }

    public function setWillingToTravel(bool $willingToTravel): static
    {
        $this->willingToTravel = $willingToTravel;

        return $this;
    }

    public function getPreferredPatientVolume(): ?int
    {
        return $this->preferredPatientVolume;
    }

    public function setPreferredPatientVolume(?int $preferredPatientVolume): static
    {
        $this->preferredPatientVolume = $preferredPatientVolume;

        return $this;
    }

    public function getReleaseAuthorizationDate(): ?\DateTimeInterface
    {
        return $this->releaseAuthorizationDate;
    }

    public function setReleaseAuthorizationDate(?\DateTimeInterface $releaseAuthorizationDate): static
    {
        $this->releaseAuthorizationDate = $releaseAuthorizationDate;

        return $this;
    }

    public function getSsn(): ?string
    {
        return $this->ssn;
    }

    public function setSsn(?string $ssn): static
    {
        $this->ssn = $ssn;

        return $this;
    }

    public function getDob(): ?\DateTimeInterface
    {
        return $this->dob;
    }

    public function setDob(?\DateTimeInterface $dob): static
    {
        $this->dob = $dob;

        return $this;
    }

    public function getCountryOfBirth(): ?string
    {
        return $this->countryOfBirth;
    }

    public function setCountryOfBirth(?string $countryOfBirth): static
    {
        $this->countryOfBirth = $countryOfBirth;

        return $this;
    }

    public function getStateOfBirth(): ?string
    {
        return $this->stateOfBirth;
    }

    public function setStateOfBirth(?string $stateOfBirth): static
    {
        $this->stateOfBirth = $stateOfBirth;

        return $this;
    }

    public function getCityOfBirth(): ?string
    {
        return $this->cityOfBirth;
    }

    public function setCityOfBirth(?string $cityOfBirth): static
    {
        $this->cityOfBirth = $cityOfBirth;

        return $this;
    }

    public function isReleaseAndAuthorizationAccepted(): ?bool
    {
        return $this->releaseAndAuthorizationAccepted;
    }

    public function setReleaseAndAuthorizationAccepted(?bool $releaseAndAuthorizationAccepted): static
    {
        $this->releaseAndAuthorizationAccepted = $releaseAndAuthorizationAccepted;

        return $this;
    }

    public function getApplicationCertificationDate(): ?\DateTimeInterface
    {
        return $this->applicationCertificationDate;
    }

    public function setApplicationCertificationDate(?\DateTimeInterface $applicationCertificationDate): void
    {
        $this->applicationCertificationDate = $applicationCertificationDate;
    }

    public function getRiskAssessment(): ?array
    {
        return $this->riskAssessment;
    }

    public function setRiskAssessment(?array $riskAssessment): static
    {
        $this->riskAssessment = $riskAssessment;

        return $this;
    }

    public function getHealthAssessment(): ?array
    {
        return $this->healthAssessment;
    }

    public function setHealthAssessment(?array $healthAssessment): static
    {
        $this->healthAssessment = $healthAssessment;

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

    /**
     * @return Collection<int, Speciality>
     */
    public function getSpecialities(): Collection
    {
        return $this->specialities;
    }

    public function addSpeciality(Speciality $speciality): static
    {
        if (!$this->specialities->contains($speciality)) {
            $this->specialities->add($speciality);
        }

        return $this;
    }

    public function removeSpeciality(Speciality $speciality): static
    {
        $this->specialities->removeElement($speciality);

        return $this;
    }

    public function getCashback(): ?array
    {
        return $this->cashback;
    }

    public function setCashback(?array $cashback): static
    {
        $this->cashback = $cashback;

        return $this;
    }

    public function getNotificationPreferences(): ?array
    {
        return $this->notificationPreferences;
    }

    public function setNotificationPreferences(?array $notificationPreferences): void
    {
        $this->notificationPreferences = $notificationPreferences;
    }

    public function getAveragePoint(): ?float
    {
        return $this->averagePoint;
    }

    public function setAveragePoint(?float $averagePoint): static
    {
        $this->averagePoint = $averagePoint;

        return $this;
    }

    public function isSkipOnboarding(): ?bool
    {
        return $this->skip_onboarding;
    }

    public function setSkipOnboarding(?bool $skip_onboarding): static
    {
        $this->skip_onboarding = $skip_onboarding;

        return $this;
    }

    public function getApplications(): Collection
    {
        return $this->applications;
    }

    public function setApplications(Collection $applications): void
    {
        $this->applications = $applications;
    }
}

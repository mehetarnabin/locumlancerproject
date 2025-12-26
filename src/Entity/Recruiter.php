<?php

namespace App\Entity;

use App\Repository\RecruiterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RecruiterRepository::class)]
#[ORM\Table(name: 'b_recruiter')]
class Recruiter
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\OneToOne(inversedBy: 'recruiter', targetEntity: User::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $speciality = null;

    #[ORM\Column(length: 50, options: ['default' => 'Silver'])]
    private ?string $membershipLevel = 'Silver';

    #[ORM\Column(type: 'decimal', precision: 3, scale: 2, options: ['default' => 0.00])]
    private ?float $rating = 0.00;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $isVerified = false;

    #[ORM\OneToMany(mappedBy: 'recruiter', targetEntity: JobRecruiter::class)]
    private Collection $jobRecruiters;

    #[ORM\OneToMany(mappedBy: 'recruiter', targetEntity: Application::class)]
    private Collection $applications;

    #[ORM\OneToMany(mappedBy: 'recruiter', targetEntity: Invoice::class)]
    private Collection $invoices;

    public function __construct()
    {
        $this->jobRecruiters = new ArrayCollection();
        $this->applications = new ArrayCollection();
        $this->invoices = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->companyName ?? $this->user->getName() ?? 'Recruiter';
    }

    public function __toString(): string
    {
        return $this->companyName ?? $this->user->getName() ?? 'Recruiter';
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): static
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getSpeciality(): ?string
    {
        return $this->speciality;
    }

    public function setSpeciality(?string $speciality): static
    {
        $this->speciality = $speciality;

        return $this;
    }

    public function getMembershipLevel(): ?string
    {
        return $this->membershipLevel;
    }

    public function setMembershipLevel(string $membershipLevel): static
    {
        $this->membershipLevel = $membershipLevel;

        return $this;
    }

    public function getRating(): ?float
    {
        return $this->rating;
    }

    public function setRating(?float $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    /**
     * @return Collection<int, JobRecruiter>
     */
    public function getJobRecruiters(): Collection
    {
        return $this->jobRecruiters;
    }

    public function addJobRecruiter(JobRecruiter $jobRecruiter): static
    {
        if (!$this->jobRecruiters->contains($jobRecruiter)) {
            $this->jobRecruiters->add($jobRecruiter);
            $jobRecruiter->setRecruiter($this);
        }

        return $this;
    }

    public function removeJobRecruiter(JobRecruiter $jobRecruiter): static
    {
        if ($this->jobRecruiters->removeElement($jobRecruiter)) {
            // set the owning side to null (unless already changed)
            if ($jobRecruiter->getRecruiter() === $this) {
                // $jobRecruiter->setRecruiter(null); // This is non-nullable side
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Application>
     */
    public function getApplications(): Collection
    {
        return $this->applications;
    }

    public function addApplication(Application $application): static
    {
        if (!$this->applications->contains($application)) {
            $this->applications->add($application);
            $application->setRecruiter($this);
        }

        return $this;
    }

    public function removeApplication(Application $application): static
    {
        if ($this->applications->removeElement($application)) {
            if ($application->getRecruiter() === $this) {
                $application->setRecruiter(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    public function addInvoice(Invoice $invoice): static
    {
        if (!$this->invoices->contains($invoice)) {
            $this->invoices->add($invoice);
            $invoice->setRecruiter($this);
        }

        return $this;
    }

    public function removeInvoice(Invoice $invoice): static
    {
        if ($this->invoices->removeElement($invoice)) {
            if ($invoice->getRecruiter() === $this) {
                $invoice->setRecruiter(null);
            }
        }

        return $this;
    }
}

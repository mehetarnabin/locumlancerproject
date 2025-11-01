<?php

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'b_review')]
#[ORM\Entity(repositoryClass: ReviewRepository::class)]
class Review
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Provider $provider = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Employer $employer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Application $application = null;

    #[ORM\Column(length: 255)]
    private ?string $message = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reviewedBy = null;

    #[ORM\Column(nullable: true)]
    private ?float $point = null;

    #[ORM\Column(nullable: true)]
    private ?int $professionalism = null;

    #[ORM\Column(nullable: true)]
    private ?int $quality = null;

    #[ORM\Column(nullable: true)]
    private ?int $communication = null;

    #[ORM\Column(nullable: true)]
    private ?int $emotionalIntelligence = null;

    use TimestampableEntity;

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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getApplication(): ?Application
    {
        return $this->application;
    }

    public function setApplication(?Application $application): void
    {
        $this->application = $application;
    }

    public function getReviewedBy(): ?string
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?string $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;

        return $this;
    }

    public function getPoint(): ?float
    {
        return $this->point;
    }

    public function setPoint(?float $point): static
    {
        $this->point = $point;

        return $this;
    }

    public function getProfessionalism(): ?int
    {
        return $this->professionalism;
    }

    public function setProfessionalism(?int $professionalism): static
    {
        $this->professionalism = $professionalism;

        return $this;
    }

    public function getQuality(): ?int
    {
        return $this->quality;
    }

    public function setQuality(?int $quality): static
    {
        $this->quality = $quality;

        return $this;
    }

    public function getCommunication(): ?int
    {
        return $this->communication;
    }

    public function setCommunication(?int $communication): static
    {
        $this->communication = $communication;

        return $this;
    }

    public function getEmotionalIntelligence(): ?int
    {
        return $this->emotionalIntelligence;
    }

    public function setEmotionalIntelligence(?int $emotionalIntelligence): static
    {
        $this->emotionalIntelligence = $emotionalIntelligence;

        return $this;
    }
}

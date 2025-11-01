<?php

namespace App\Entity;

use App\Repository\ApplicationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'b_application')]
#[ORM\Entity(repositoryClass: ApplicationRepository::class)]
class Application
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    private ?Employer $employer = null;

    #[ORM\ManyToOne]
    private ?Provider $provider = null;

    #[ORM\ManyToOne]
    private ?Job $job = null;

    #[ORM\Column(length: 40)]
    private ?string $status = 'applied';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $documentRequestedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $documentProvidedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $oneFileRequestedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $oneFileProvidedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $contractSentAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contractFileName = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $contractSignedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contractSignedFileName = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?Interview $interview = null;

    use TimestampableEntity;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEmployer(): ?Employer
    {
        return $this->employer;
    }

    public function setEmployer(?Employer $employer): void
    {
        $this->employer = $employer;
    }

    public function getProvider(): ?Provider
    {
        return $this->provider;
    }

    public function setProvider(?Provider $provider): void
    {
        $this->provider = $provider;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getJob(): ?Job
    {
        return $this->job;
    }

    public function setJob(?Job $job): static
    {
        $this->job = $job;

        return $this;
    }

    public function getDocumentRequestedAt(): ?\DateTimeInterface
    {
        return $this->documentRequestedAt;
    }

    public function setDocumentRequestedAt(?\DateTimeInterface $documentRequestedAt): static
    {
        $this->documentRequestedAt = $documentRequestedAt;

        return $this;
    }

    public function getDocumentProvidedAt(): ?\DateTimeInterface
    {
        return $this->documentProvidedAt;
    }

    public function setDocumentProvidedAt(?\DateTimeInterface $documentProvidedAt): static
    {
        $this->documentProvidedAt = $documentProvidedAt;

        return $this;
    }

    public function getOneFileRequestedAt(): ?\DateTimeInterface
    {
        return $this->oneFileRequestedAt;
    }

    public function setOneFileRequestedAt(?\DateTimeInterface $oneFileRequestedAt): void
    {
        $this->oneFileRequestedAt = $oneFileRequestedAt;
    }

    public function getOneFileProvidedAt(): ?\DateTimeInterface
    {
        return $this->oneFileProvidedAt;
    }

    public function setOneFileProvidedAt(?\DateTimeInterface $oneFileProvidedAt): void
    {
        $this->oneFileProvidedAt = $oneFileProvidedAt;
    }

    public function getContractSentAt(): ?\DateTimeInterface
    {
        return $this->contractSentAt;
    }

    public function setContractSentAt(?\DateTimeInterface $contractSentAt): static
    {
        $this->contractSentAt = $contractSentAt;

        return $this;
    }

    public function getContractFileName(): ?string
    {
        return $this->contractFileName;
    }

    public function setContractFileName(?string $contractFileName): static
    {
        $this->contractFileName = $contractFileName;

        return $this;
    }

    public function getContractSignedAt(): ?\DateTimeInterface
    {
        return $this->contractSignedAt;
    }

    public function setContractSignedAt(?\DateTimeInterface $contractSignedAt): void
    {
        $this->contractSignedAt = $contractSignedAt;
    }

    public function getContractSignedFileName(): ?string
    {
        return $this->contractSignedFileName;
    }

    public function setContractSignedFileName(?string $contractSignedFileName): void
    {
        $this->contractSignedFileName = $contractSignedFileName;
    }

    public function getInterview(): ?Interview
    {
        return $this->interview;
    }

    public function setInterview(?Interview $interview): static
    {
        $this->interview = $interview;

        return $this;
    }
}

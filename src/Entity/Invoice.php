<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Symfony\Component\Uid\Uuid;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\InvoiceRepository;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Table(name: 'b_invoice')]
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
class Invoice
{
    const INVOICE_STATUS_PENDING = 'pending';
    const INVOICE_STATUS_PAID = 'paid';

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

    #[ORM\Column]
    private ?int $amount = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $particular = null;

    #[ORM\Column(length: 255)]
    private ?string $status = self::INVOICE_STATUS_PENDING;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $invoiceNumber = null;

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

    public function getJob(): ?Job
    {
        return $this->job;
    }

    public function setJob(?Job $job): static
    {
        $this->job = $job;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getParticular(): ?string
    {
        return $this->particular;
    }

    public function setParticular(string $particular): static
    {
        $this->particular = $particular;

        return $this;
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

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(?string $invoiceNumber): static
    {
        $this->invoiceNumber = $invoiceNumber;

        return $this;
    }
}

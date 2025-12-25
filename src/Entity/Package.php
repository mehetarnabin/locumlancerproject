<?php

namespace App\Entity;

use App\Repository\PackageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'b_package')]
#[ORM\Entity(repositoryClass: PackageRepository::class)]
class Package
{
    use TimestampableEntity;

    const TYPE_SILVER = 'silver';
    const TYPE_GOLD = 'gold';
    const TYPE_DIAMOND = 'diamond';

    const TARGET_PROVIDER = 'provider';
    const TARGET_EMPLOYER = 'employer';
    const TARGET_RECRUITER = 'recruiter';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 50)]
    private ?string $name = null;

    #[ORM\Column(length: 20)]
    private ?string $type = self::TYPE_SILVER;

    #[ORM\Column(length: 20)]
    private ?string $target = self::TARGET_PROVIDER;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $durationDays = 30;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $maxJobPosts = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $maxApplications = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $features = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePriceId = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(string $target): static
    {
        $this->target = $target;
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

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getDurationDays(): ?int
    {
        return $this->durationDays;
    }

    public function setDurationDays(int $durationDays): static
    {
        $this->durationDays = $durationDays;
        return $this;
    }

    public function getMaxJobPosts(): ?int
    {
        return $this->maxJobPosts;
    }

    public function setMaxJobPosts(?int $maxJobPosts): static
    {
        $this->maxJobPosts = $maxJobPosts;
        return $this;
    }

    public function getMaxApplications(): ?int
    {
        return $this->maxApplications;
    }

    public function setMaxApplications(?int $maxApplications): static
    {
        $this->maxApplications = $maxApplications;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function getFeatures(): ?array
    {
        return $this->features;
    }

    public function setFeatures(?array $features): static
    {
        $this->features = $features;
        return $this;
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_SILVER => 'Silver',
            self::TYPE_GOLD => 'Gold',
            self::TYPE_DIAMOND => 'Diamond',
            default => ucfirst($this->type)
        };
    }

    public function getTargetLabel(): string
    {
        return match ($this->target) {
            self::TARGET_PROVIDER => 'Provider',
            self::TARGET_EMPLOYER => 'Employer',
            self::TARGET_RECRUITER => 'Recruiter',
            default => ucfirst($this->target)
        };
    }

    public static function getTargetChoices(): array
    {
        return [
            'Provider' => self::TARGET_PROVIDER,
            'Employer' => self::TARGET_EMPLOYER,
            'Recruiter' => self::TARGET_RECRUITER,
        ];
    }

    public static function getTypeChoices(): array
    {
        return [
            'Silver' => self::TYPE_SILVER,
            'Gold' => self::TYPE_GOLD,
            'Diamond' => self::TYPE_DIAMOND,
        ];
    }

    public function getStripePriceId(): ?string
    {
        return $this->stripePriceId;
    }

    public function setStripePriceId(?string $stripePriceId): static
    {
        $this->stripePriceId = $stripePriceId;
        return $this;
    }
}

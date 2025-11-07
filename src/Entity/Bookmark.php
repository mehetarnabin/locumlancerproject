<?php

namespace App\Entity;

use App\Repository\BookmarkRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'b_bookmark')]
#[ORM\Entity(repositoryClass: BookmarkRepository::class)]
class Bookmark
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    private ?User $user = null;

    #[ORM\ManyToOne]
    private ?Job $job = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $rank = null;

    // --- Getters & Setters ---

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

    public function getJob(): ?Job
    {
        return $this->job;
    }

    public function setJob(?Job $job): void
    {
        $this->job = $job;
    }

    public function getRank(): ?float
    {
        return $this->rank;
    }

    public function setRank(?float $rank): self
    {
        $this->rank = $rank;
        return $this;
    }


public function getMatchPercentage(): float
{
    // Adjust according to your actual matching logic
    if ($this->rank && $this->rankMax) {
        return round(($this->rank / $this->rankMax) * 100, 1);
    }

    return 0;
}

}

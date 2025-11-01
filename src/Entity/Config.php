<?php

namespace App\Entity;

use App\Repository\ConfigRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Uid\Uuid;
use Gedmo\Loggable\Loggable;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Table(name: 'b_config')]
#[ORM\Entity(repositoryClass: ConfigRepository::class)]
class Config implements Loggable
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $configKey;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $configValue = null;

    use TimestampableEntity;

    public function getConfigKey(): ?string
    {
        return $this->configKey;
    }

    public function setConfigKey(string $key): self
    {
        $this->configKey = $key;

        return $this;
    }

    public function getConfigValue(): ?string
    {
        return $this->configValue;
    }

    public function setConfigValue(?string $value): self
    {
        $this->configValue = $value;

        return $this;
    }
}

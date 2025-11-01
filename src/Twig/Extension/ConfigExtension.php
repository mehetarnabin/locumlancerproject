<?php

namespace App\Twig\Extension;

use Twig\TwigFunction;
use App\Service\ConfigManager;
use Twig\Extension\AbstractExtension;

class ConfigExtension extends AbstractExtension
{

    private $configManager;

    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('getConfig', [$this, 'getConfig'])
        ];
    }

    public function getConfig($key, $default = null)
    {
        return $this->configManager->get($key, $default);
    }

    public function getName()
    {
        return 'app_config_extension';
    }
}

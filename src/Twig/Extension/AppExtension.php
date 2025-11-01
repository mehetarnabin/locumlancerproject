<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\AppExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // If your filter generates SAFE HTML, you should add a third
            // parameter: ['is_safe' => ['html']]
            // Reference: https://twig.symfony.com/doc/3.x/advanced.html#automatic-escaping
            new TwigFilter('filter_name', [AppExtensionRuntime::class, 'doSomething']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('function_name', [AppExtensionRuntime::class, 'doSomething']),
            new TwigFunction('getProfessions', [AppExtensionRuntime::class, 'getProfessions']),
            new TwigFunction('getSpecialities', [AppExtensionRuntime::class, 'getSpecialities']),
            new TwigFunction('getUSStates', [AppExtensionRuntime::class, 'getUSStates']),
            new TwigFunction('getHeaderNotifications', [AppExtensionRuntime::class, 'getHeaderNotifications']),
            new TwigFunction('getUnseenMessageCount', [AppExtensionRuntime::class, 'getUnseenMessageCount']),
            new TwigFunction('getUnseenNotificationCount', [AppExtensionRuntime::class, 'getUnseenNotificationCount']),
        ];
    }
}

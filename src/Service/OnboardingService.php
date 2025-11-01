<?php

namespace App\Service;

class OnboardingService
{
    public function isProviderOnboardingCompleted($user): bool
    {
        $provider = $user->getProvider();

        if (
            !is_null($provider->getName()) &&
            !is_null($provider->getNpiNumber()) &&
            !is_null($provider->getPhoneMobile()) &&
            !is_null($provider->getCvFilename())
        ) {
            return true;
        }

        return false;
    }
}
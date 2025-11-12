<?php

namespace App\Event;

use App\Entity\Application;
use Symfony\Contracts\EventDispatcher\Event;

class HireEvent extends Event
{
    public const APPLICATION_HIRED = 'application.hired';

    public function __construct(
        private Application $application
    ) {}

    public function getApplication(): Application
    {
        return $this->application;
    }
}
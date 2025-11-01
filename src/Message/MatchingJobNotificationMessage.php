<?php

namespace App\Message;

class MatchingJobNotificationMessage
{
    public function __construct(
        public string $jobId
    ){}
}
<?php

namespace App\Event;

use App\Entity\Job;

class JobEvent
{
    public const JOB_CREATED = 'job.created';

    public function __construct(private Job $job){}

    public function getJob(): Job
    {
        return $this->job;
    }
}
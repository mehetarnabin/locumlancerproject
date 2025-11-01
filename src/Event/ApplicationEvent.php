<?php

namespace App\Event;

use App\Entity\Application;

class ApplicationEvent
{
    public const APPLICATION_CREATED = 'application.created';
    public const APPLICATION_DOCUMENT_REQUESTED = 'application.document_requested';
    public const APPLICATION_DOCUMENT_PROVIDED = 'application.document_provided';
    public const APPLICATION_CONTRACT_SENT = 'application.contract_sent';
    public const APPLICATION_CONTRACT_SIGNED_SENT = 'application.contract_signed_sent';
    public const APPLICATION_ONE_FILE_REQUESTED = 'application.one_file_requested';
    public const APPLICATION_ONE_FILE_PROVIDED = 'application.one_file_provided';

    public const APPLICATION_INTERVIEW_SCHEDULED = 'application.interview_scheduled';

    public function __construct(private Application $application){}

    public function getApplication(): Application
    {
        return $this->application;
    }
}
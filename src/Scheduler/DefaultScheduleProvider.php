<?php

namespace App\Scheduler;

use App\Message\InvoiceOverdueNotificationMessage;
use Symfony\Component\Scheduler\Schedule;
use App\Message\JobExpirationNotificationMessage;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('default')]
final class DefaultScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(){}

    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::every('1 day', new JobExpirationNotificationMessage()),
            RecurringMessage::every('1 day', new InvoiceOverdueNotificationMessage()),
        );
    }
}

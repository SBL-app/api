<?php

namespace App\Scheduler;

use App\Message\GameResultTimeoutMessage;
use App\Message\MatchReminderMessage;
use App\Message\MatchResultTimeoutMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('default')]
class MatchReminderSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::cron('0 * * * *', new MatchReminderMessage()))
            ->add(RecurringMessage::cron('0 * * * *', new MatchResultTimeoutMessage()))
            ->add(RecurringMessage::cron('0 * * * *', new GameResultTimeoutMessage()));
    }
}

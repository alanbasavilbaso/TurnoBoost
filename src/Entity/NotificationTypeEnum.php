<?php

namespace App\Entity;

enum NotificationTypeEnum: string
{
    case CONFIRMATION = 'confirmation';
    case REMINDER = 'reminder';
    case URGENT_REMINDER = 'urgent_reminder';
    case CANCELLATION = 'cancellation';
}
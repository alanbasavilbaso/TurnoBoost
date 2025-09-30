<?php

namespace App\Entity;

enum NotificationTypeEnum: string
{
    case CONFIRMATION = 'confirmation';
    case REMINDER = 'reminder';
    case URGENT_REMINDER = 'urgent';
    case CANCELLATION = 'cancellation';
    case MODIFICATION = 'modification';
}
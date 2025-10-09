<?php

namespace App\Entity;

enum NotificationTypeEnum: string
{
    case CONFIRMATION = 'confirmation';
    case REMINDER = 'reminder';
    case URGENT_REMINDER = 'urgent';
    case CANCELLATION = 'cancellation';
    case MODIFICATION = 'modification';
    case COMPANY_NEW_BOOKING = 'company_new_booking';
    case COMPANY_CANCELLATION = 'company_cancellation';
}
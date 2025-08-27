<?php

namespace App\Entity;

enum NotificationStatusEnum: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
}
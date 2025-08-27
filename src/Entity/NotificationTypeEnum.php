<?php

namespace App\Entity;

enum NotificationTypeEnum: string
{
    case WHATSAPP = 'whatsapp';
    case SMS = 'sms';
    case EMAIL = 'email';
}
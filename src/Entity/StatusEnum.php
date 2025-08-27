<?php

namespace App\Entity;

enum StatusEnum: string
{
    case SCHEDULED = 'scheduled';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case NO_SHOW = 'no_show';
    case COMPLETED = 'completed';
}
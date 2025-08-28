<?php

namespace App\Entity;

enum ServiceTypeEnum: string
{
    case REGULAR = 'regular';
    case QUOTA_BASED = 'quota_based';
    case RECURRING = 'recurring';

    public function getLabel(): string
    {
        return match($this) {
            self::REGULAR => 'Regular',
            self::QUOTA_BASED => 'Por Cupos',
            self::RECURRING => 'Recurrente',
        };
    }

    public static function getChoices(): array
    {
        return [
            'Regular' => self::REGULAR->value,
            'Por Cupos' => self::QUOTA_BASED->value,
            'Recurrente' => self::RECURRING->value,
        ];
    }
}
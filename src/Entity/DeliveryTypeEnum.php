<?php

namespace App\Entity;

enum DeliveryTypeEnum: string
{
    case IN_PERSON = 'in_person';
    case ONLINE = 'online';

    public function getLabel(): string
    {
        return match($this) {
            self::IN_PERSON => 'Presencial',
            self::ONLINE => 'Online',
        };
    }

    public static function getChoices(): array
    {
        return [
            'Presencial' => self::IN_PERSON->value,
            'Online' => self::ONLINE->value,
        ];
    }
}
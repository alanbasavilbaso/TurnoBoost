<?php

namespace App\Message;

class SendEmailNotification
{
    public function __construct(
        private int $notificationId,
        private string $type  // ← AGREGAR ESTE PARÁMETRO
    ) {}

    public function getNotificationId(): int
    {
        return $this->notificationId;
    }
    
    public function getType(): string  // ← AGREGAR ESTE MÉTODO
    {
        return $this->type;
    }
}
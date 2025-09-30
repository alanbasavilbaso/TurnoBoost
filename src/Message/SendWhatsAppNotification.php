<?php

namespace App\Message;

class SendWhatsAppNotification
{
    public function __construct(
        private int $notificationId,
        private string $type
    ) {}

    public function getNotificationId(): int
    {
        return $this->notificationId;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
}
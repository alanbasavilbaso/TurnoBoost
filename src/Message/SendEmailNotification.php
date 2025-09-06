<?php

namespace App\Message;

class SendEmailNotification
{
    public function __construct(
        private int $notificationId
    ) {}

    public function getNotificationId(): int
    {
        return $this->notificationId;
    }
}
<?php

namespace App\Message;

class SendWhatsAppNotification
{
    private int $appointmentId;
    private string $messageType;

    public function __construct(int $appointmentId, string $messageType)
    {
        $this->appointmentId = $appointmentId;
        $this->messageType = $messageType;
    }

    public function getAppointmentId(): int
    {
        return $this->appointmentId;
    }

    public function getMessageType(): string
    {
        return $this->messageType;
    }
}
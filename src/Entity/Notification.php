<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Appointment::class, inversedBy: 'notifications')]
    #[ORM\JoinColumn(name: 'appointment_id', referencedColumnName: 'id', nullable: false)]
    private Appointment $appointment;

    #[ORM\Column(type: 'string', enumType: NotificationTypeEnum::class)]
    private NotificationTypeEnum $type;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $templateUsed = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $sentAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $scheduledAt = null;

    // Agregar métodos getter/setter:
    public function getScheduledAt(): ?\DateTimeInterface
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeInterface $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;
        return $this;
    }

    #[ORM\Column(type: 'string', enumType: NotificationStatusEnum::class)]
    private NotificationStatusEnum $status = NotificationStatusEnum::PENDING;

    public function __construct()
    {
        $this->status = NotificationStatusEnum::PENDING;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAppointment(): Appointment
    {
        return $this->appointment;
    }

    public function setAppointment(Appointment $appointment): static
    {
        $this->appointment = $appointment;
        return $this;
    }

    public function getType(): NotificationTypeEnum
    {
        return $this->type;
    }

    public function setType(NotificationTypeEnum $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTemplateUsed(): ?string
    {
        return $this->templateUsed;
    }

    public function setTemplateUsed(?string $templateUsed): static
    {
        $this->templateUsed = $templateUsed;
        return $this;
    }

    public function getSentAt(): ?\DateTimeInterface
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeInterface $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getStatus(): NotificationStatusEnum
    {
        return $this->status;
    }

    public function setStatus(NotificationStatusEnum $status): static
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Marca la notificación como enviada
     */
    public function markAsSent(): static
    {
        $this->status = NotificationStatusEnum::SENT;
        $this->sentAt = new \DateTime();
        return $this;
    }

    /**
     * Marca la notificación como fallida
     */
    public function markAsFailed(): static
    {
        $this->status = NotificationStatusEnum::FAILED;
        return $this;
    }

    /**
     * Verifica si la notificación está pendiente
     */
    public function isPending(): bool
    {
        return $this->status === NotificationStatusEnum::PENDING;
    }

    /**
     * Verifica si la notificación fue enviada
     */
    public function isSent(): bool
    {
        return $this->status === NotificationStatusEnum::SENT;
    }

    /**
     * Verifica si la notificación falló
     */
    public function isFailed(): bool
    {
        return $this->status === NotificationStatusEnum::FAILED;
    }
}
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'schedule_blocks')]
class ScheduleBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Professional::class, inversedBy: 'scheduleBlocks')]
    #[ORM\JoinColumn(name: 'professional_id', referencedColumnName: 'id', nullable: false)]
    private Professional $professional;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $startDatetime;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $endDatetime;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    // Getters y Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfessional(): Professional
    {
        return $this->professional;
    }

    public function setProfessional(Professional $professional): self
    {
        $this->professional = $professional;
        return $this;
    }

    public function getStartDatetime(): \DateTimeInterface
    {
        return $this->startDatetime;
    }

    public function setStartDatetime(\DateTimeInterface $startDatetime): self
    {
        $this->startDatetime = $startDatetime;
        return $this;
    }

    public function getEndDatetime(): \DateTimeInterface
    {
        return $this->endDatetime;
    }

    public function setEndDatetime(\DateTimeInterface $endDatetime): self
    {
        $this->endDatetime = $endDatetime;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    /**
     * Verifica si el bloque está activo en una fecha/hora específica
     */
    public function isActiveAt(\DateTimeInterface $datetime): bool
    {
        return $datetime >= $this->startDatetime && $datetime <= $this->endDatetime;
    }

    /**
     * Obtiene la duración del bloque en minutos
     */
    public function getDurationInMinutes(): int
    {
        return (int) $this->startDatetime->diff($this->endDatetime)->format('%i');
    }
}
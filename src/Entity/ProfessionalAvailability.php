<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'professional_availability')]
class ProfessionalAvailability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Professional::class, inversedBy: 'availabilities')]
    #[ORM\JoinColumn(name: 'professional_id', referencedColumnName: 'id', nullable: false)]
    private Professional $professional;

    #[ORM\Column(type: 'integer')]
    private int $weekday; // 0=Lunes, 1=Martes, ..., 6=Domingo

    #[ORM\Column(type: 'time')]
    private \DateTimeInterface $startTime;

    #[ORM\Column(type: 'time')]
    private \DateTimeInterface $endTime;

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

    public function getWeekday(): int
    {
        return $this->weekday;
    }

    public function setWeekday(int $weekday): self
    {
        if ($weekday < 0 || $weekday > 6) {
            throw new \InvalidArgumentException('Weekday must be between 0 (Monday) and 6 (Sunday)');
        }
        $this->weekday = $weekday;
        return $this;
    }

    public function getStartTime(): \DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): \DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;
        return $this;
    }

    // Métodos auxiliares
    public function getWeekdayName(): string
    {
        $days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        return $days[$this->weekday] ?? 'Desconocido';
    }

    public function getFormattedTimeRange(): string
    {
        return $this->startTime->format('H:i') . ' - ' . $this->endTime->format('H:i');
    }

    public function getDurationInMinutes(): int
    {
        $start = clone $this->startTime;
        $end = clone $this->endTime;
        
        // Si el horario cruza medianoche
        if ($end < $start) {
            $end->modify('+1 day');
        }
        
        return ($end->getTimestamp() - $start->getTimestamp()) / 60;
    }

    public function isTimeInRange(\DateTimeInterface $time): bool
    {
        $timeOnly = $time->format('H:i:s');
        $startOnly = $this->startTime->format('H:i:s');
        $endOnly = $this->endTime->format('H:i:s');
        
        return $timeOnly >= $startOnly && $timeOnly <= $endOnly;
    }
}
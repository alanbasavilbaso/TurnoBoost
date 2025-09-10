<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'location_availability')]
class LocationAvailability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Location::class, inversedBy: 'availabilities')]
    #[ORM\JoinColumn(name: 'location_id', referencedColumnName: 'id', nullable: true)]
    private ?Location $location = null;

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): self
    {
        $this->location = $location;
        return $this;
    }

    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 0, max: 6, notInRangeMessage: 'El día de la semana debe estar entre 0 (Lunes) y 6 (Domingo)')]
    private int $weekDay; // 0=Lunes, 1=Martes, ..., 6=Domingo

    #[ORM\Column(type: 'time')]
    #[Assert\NotNull(message: 'La hora de inicio es obligatoria')]
    private \DateTimeInterface $startTime;

    #[ORM\Column(type: 'time')]
    #[Assert\NotNull(message: 'La hora de fin es obligatoria')]
    private \DateTimeInterface $endTime;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWeekDay(): int
    {
        return $this->weekDay;
    }

    public function setWeekDay(int $weekDay): self
    {
        if ($weekDay < 0 || $weekDay > 6) {
            throw new \InvalidArgumentException('El día de la semana debe estar entre 0 (Lunes) y 6 (Domingo)');
        }
        $this->weekDay = $weekDay;
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

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // Métodos auxiliares
    public function getWeekDayName(): string
    {
        $days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        return $days[$this->weekDay] ?? 'Desconocido';
    }

    /**
     * Verifica si una hora específica está dentro del rango de este horario
     */
    public function isTimeInRange(\DateTimeInterface $time): bool
    {
        $timeStr = $time->format('H:i:s');
        $startStr = $this->startTime->format('H:i:s');
        $endStr = $this->endTime->format('H:i:s');
        
        return $timeStr >= $startStr && $timeStr <= $endStr;
    }

    /**
     * Obtiene el horario formateado para mostrar
     */
    public function getFormattedSchedule(): string
    {
        return sprintf(
            '%s: %s - %s',
            $this->getWeekDayName(),
            $this->startTime->format('H:i'),
            $this->endTime->format('H:i')
        );
    }
}
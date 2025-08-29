<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'professional_services')]
class ProfessionalService
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Professional::class, inversedBy: 'professionalServices')]
    #[ORM\JoinColumn(name: 'professional_id', referencedColumnName: 'id', nullable: false)]
    private Professional $professional;

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'professionalServices')]
    #[ORM\JoinColumn(name: 'service_id', referencedColumnName: 'id', nullable: false)]
    private Service $service;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $customDurationMinutes = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $customPrice = null;

    // Campos para días de la semana disponibles (0=Lunes, 6=Domingo)
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $availableMonday = true; // 0

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $availableTuesday = true; // 1

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $availableWednesday = true; // 2

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $availableThursday = true; // 3

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $availableFriday = true; // 4

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $availableSaturday = true; // 5

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $availableSunday = true; // 6

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfessional(): ?Professional
    {
        return $this->professional;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function getCustomDurationMinutes(): ?int
    {
        return $this->customDurationMinutes;
    }

    public function getCustomPrice(): ?string
    {
        return $this->customPrice;
    }

    // Setters
    public function setProfessional(?Professional $professional): static
    {
        $this->professional = $professional;
        return $this;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;
        return $this;
    }

    public function setCustomDurationMinutes(?int $customDurationMinutes): static
    {
        $this->customDurationMinutes = $customDurationMinutes;
        return $this;
    }

    public function setCustomPrice(?string $customPrice): static
    {
        $this->customPrice = $customPrice;
        return $this;
    }

    // Métodos auxiliares
    
    /**
     * Obtiene la duración efectiva del servicio (personalizada o por defecto)
     */
    public function getEffectiveDuration(): int
    {
        return $this->customDurationMinutes ?? $this->service?->getDurationMinutes() ?? 30;
    }

    /**
     * Obtiene el precio efectivo del servicio (personalizado o por defecto)
     */
    public function getEffectivePrice(): float
    {
        if ($this->customPrice !== null) {
            return (float) $this->customPrice;
        }
        
        return $this->service?->getPrice() ?? 0.0;
    }

    /**
     * Verifica si tiene configuración personalizada
     */
    public function hasCustomConfiguration(): bool
    {
        return $this->customDurationMinutes !== null || $this->customPrice !== null;
    }

    /**
     * Obtiene el precio formateado como string
     */
    public function getFormattedPrice(): string
    {
        return number_format($this->getEffectivePrice(), 2);
    }

    /**
     * Obtiene la duración formateada como string
     */
    public function getFormattedDuration(): string
    {
        $minutes = $this->getEffectiveDuration();
        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($hours > 0) {
            return $hours . 'h ' . ($remainingMinutes > 0 ? $remainingMinutes . 'min' : '');
        }
        
        return $minutes . ' min';
    }

    // Nuevos getters para días de la semana
    public function isAvailableMonday(): bool
    {
        return $this->availableMonday;
    }

    public function isAvailableTuesday(): bool
    {
        return $this->availableTuesday;
    }

    public function isAvailableWednesday(): bool
    {
        return $this->availableWednesday;
    }

    public function isAvailableThursday(): bool
    {
        return $this->availableThursday;
    }

    public function isAvailableFriday(): bool
    {
        return $this->availableFriday;
    }

    public function isAvailableSaturday(): bool
    {
        return $this->availableSaturday;
    }

    public function isAvailableSunday(): bool
    {
        return $this->availableSunday;
    }

    // Nuevos setters para días de la semana
    public function setAvailableMonday(bool $availableMonday): static
    {
        $this->availableMonday = $availableMonday;
        return $this;
    }

    public function setAvailableTuesday(bool $availableTuesday): static
    {
        $this->availableTuesday = $availableTuesday;
        return $this;
    }

    public function setAvailableWednesday(bool $availableWednesday): static
    {
        $this->availableWednesday = $availableWednesday;
        return $this;
    }

    public function setAvailableThursday(bool $availableThursday): static
    {
        $this->availableThursday = $availableThursday;
        return $this;
    }

    public function setAvailableFriday(bool $availableFriday): static
    {
        $this->availableFriday = $availableFriday;
        return $this;
    }

    public function setAvailableSaturday(bool $availableSaturday): static
    {
        $this->availableSaturday = $availableSaturday;
        return $this;
    }

    public function setAvailableSunday(bool $availableSunday): static
    {
        $this->availableSunday = $availableSunday;
        return $this;
    }

    // Métodos auxiliares para días de la semana (usando convención 0-6)
    
    /**
     * Verifica si el servicio está disponible en un día específico
     * @param int $dayOfWeek Día de la semana (0=Lunes, 6=Domingo)
     */
    public function isAvailableOnDay(int $dayOfWeek): bool
    {
        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            throw new \InvalidArgumentException('Weekday must be between 0 (Monday) and 6 (Sunday)');
        }
        
        return match($dayOfWeek) {
            0 => $this->availableMonday,
            1 => $this->availableTuesday,
            2 => $this->availableWednesday,
            3 => $this->availableThursday,
            4 => $this->availableFriday,
            5 => $this->availableSaturday,
            6 => $this->availableSunday,
        };
    }

    /**
     * Obtiene un array con los días disponibles (0-6)
     */
    public function getAvailableDays(): array
    {
        $days = [];
        if ($this->availableMonday) $days[] = 0;
        if ($this->availableTuesday) $days[] = 1;
        if ($this->availableWednesday) $days[] = 2;
        if ($this->availableThursday) $days[] = 3;
        if ($this->availableFriday) $days[] = 4;
        if ($this->availableSaturday) $days[] = 5;
        if ($this->availableSunday) $days[] = 6;
        return $days;
    }

    /**
     * Obtiene los nombres de los días disponibles
     */
    public function getAvailableDayNames(): array
    {
        $dayNames = [
            0 => 'Lunes',
            1 => 'Martes', 
            2 => 'Miércoles',
            3 => 'Jueves',
            4 => 'Viernes',
            5 => 'Sábado',
            6 => 'Domingo'
        ];
        
        $availableDays = $this->getAvailableDays();
        return array_map(fn($day) => $dayNames[$day], $availableDays);
    }

    /**
     * Establece la disponibilidad para un día específico
     * @param int $dayOfWeek Día de la semana (0=Lunes, 6=Domingo)
     * @param bool $available Si está disponible o no
     */
    public function setAvailabilityForDay(int $dayOfWeek, bool $available): static
    {
        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            throw new \InvalidArgumentException('Weekday must be between 0 (Monday) and 6 (Sunday)');
        }
        
        match($dayOfWeek) {
            0 => $this->availableMonday = $available,
            1 => $this->availableTuesday = $available,
            2 => $this->availableWednesday = $available,
            3 => $this->availableThursday = $available,
            4 => $this->availableFriday = $available,
            5 => $this->availableSaturday = $available,
            6 => $this->availableSunday = $available,
        };
        return $this;
    }

    /**
     * Verifica si el servicio está disponible en el mismo día que una disponibilidad del profesional
     * @param ProfessionalAvailability $availability
     */
    public function isAvailableOnSameDayAs(ProfessionalAvailability $availability): bool
    {
        return $this->isAvailableOnDay($availability->getWeekday());
    }
}

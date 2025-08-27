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
}
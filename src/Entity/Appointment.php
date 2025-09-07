<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'appointments')]
class Appointment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false)]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: Location::class, inversedBy: 'professionals')]
    #[ORM\JoinColumn(name: 'location_id', referencedColumnName: 'id', nullable: false)]
    private Location $location;

    #[ORM\ManyToOne(targetEntity: Professional::class, inversedBy: 'appointments')]
    #[ORM\JoinColumn(name: 'professional_id', referencedColumnName: 'id', nullable: false)]
    private Professional $professional;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: 'appointments')]
    #[ORM\JoinColumn(name: 'patient_id', referencedColumnName: 'id', nullable: true)]
    private ?Patient $patient = null;

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'appointments')]
    #[ORM\JoinColumn(name: 'service_id', referencedColumnName: 'id', nullable: true)]
    private ?Service $service = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $scheduledAt;

    #[ORM\Column(type: 'integer')]
    private int $durationMinutes;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?float $price = null;

    #[ORM\Column(type: 'string', enumType: StatusEnum::class)]
    private StatusEnum $status = StatusEnum::SCHEDULED;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(mappedBy: 'appointment', targetEntity: Notification::class, cascade: ['persist', 'remove'])]
    private Collection $notifications;

    #[ORM\OneToOne(mappedBy: 'appointment', targetEntity: Feedback::class, cascade: ['persist', 'remove'])]
    private ?Feedback $feedback = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->status = StatusEnum::SCHEDULED;
        $this->notifications = new ArrayCollection();
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLocation(): Location
    {
        return $this->location;
    }

    public function setLocation(Location $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getProfessional(): Professional
    {
        return $this->professional;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function getScheduledAt(): \DateTimeInterface
    {
        return $this->scheduledAt;
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function getStatus(): StatusEnum
    {
        return $this->status;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setProfessional(Professional $professional): static
    {
        $this->professional = $professional;
        return $this;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;
        return $this;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;
        return $this;
    }

    public function setScheduledAt(\DateTimeInterface $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;
        return $this;
    }

    public function setDurationMinutes(int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;
        return $this;
    }

    public function setStatus(StatusEnum $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // Métodos auxiliares
    
    /**
     * Calcula la fecha y hora de finalización de la cita
     */
    public function getEndTime(): \DateTimeInterface
    {
        return (clone $this->scheduledAt)->add(new \DateInterval('PT' . $this->durationMinutes . 'M'));
    }

    /**
     * Verifica si la cita está en el pasado
     */
    public function isPast(): bool
    {
        return $this->scheduledAt < new \DateTime();
    }

    /**
     * Verifica si la cita está en el futuro
     */
    public function isFuture(): bool
    {
        return $this->scheduledAt > new \DateTime();
    }

    /**
     * Verifica si la cita está programada para hoy
     */
    public function isToday(): bool
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        return $this->scheduledAt >= $today && $this->scheduledAt < $tomorrow;
    }

    /**
     * Verifica si la cita puede ser cancelada
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [StatusEnum::SCHEDULED, StatusEnum::CONFIRMED]) && $this->isFuture();
    }

    /**
     * Verifica si la cita puede ser confirmada
     */
    public function canBeConfirmed(): bool
    {
        return $this->status === StatusEnum::SCHEDULED && $this->isFuture();
    }

    /**
     * Verifica si la cita puede ser marcada como completada
     */
    public function canBeCompleted(): bool
    {
        return in_array($this->status, [StatusEnum::SCHEDULED, StatusEnum::CONFIRMED]);
    }

    /**
     * Obtiene el precio de la cita basado en el servicio o configuración personalizada
     */
    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): static
    {
        $this->company = $company;
        return $this;
    }

    public function getPrice(): ?float
    {
        // Si ya tiene un precio almacenado, devolverlo (precio fijo al momento de la cita)
        if ($this->price !== null) {
            return $this->price;
        }

        // Si no tiene precio almacenado, calcular dinámicamente (para compatibilidad con citas existentes)
        if ($this->service && $this->professional) {
            // Buscar si hay configuración personalizada en ProfessionalService
            foreach ($this->professional->getProfessionalServices() as $ps) {
                if ($ps->getService() === $this->service) {
                    return $ps->getEffectivePrice();
                }
            }
            // Si no hay configuración personalizada, usar precio del servicio
            return $this->service->getPriceAsFloat();
        }
        return null;
    }

    public function setPrice(?float $price): static
    {
        $this->price = $price;
        return $this;
    }

    /**
     * Establece el precio basado en el servicio y profesional actuales
     * Útil para fijar el precio al momento de crear la cita
     */
    public function setPriceFromService(): static
    {
        if ($this->service && $this->professional) {
            // Buscar si hay configuración personalizada en ProfessionalService
            foreach ($this->professional->getProfessionalServices() as $ps) {
                if ($ps->getService() === $this->service) {
                    $this->price = $ps->getEffectivePrice();
                    return $this;
                }
            }
            // Si no hay configuración personalizada, usar precio del servicio
            $this->price = $this->service->getPriceAsFloat();
        }
        return $this;
    }

    /**
     * Formatea la fecha y hora de la cita
     */
    public function getFormattedDateTime(): string
    {
        return $this->scheduledAt->format('d/m/Y H:i');
    }

    /**
     * Formatea la duración de la cita
     */
    public function getFormattedDuration(): string
    {
        $hours = intval($this->durationMinutes / 60);
        $minutes = $this->durationMinutes % 60;
        
        if ($hours > 0) {
            return $hours . 'h ' . ($minutes > 0 ? $minutes . 'min' : '');
        }
        
        return $this->durationMinutes . ' min';
    }

    /**
     * Obtiene el nombre del paciente o 'Sin asignar'
     */
    public function getPatientName(): string
    {
        return $this->patient?->getName() ?? 'Sin asignar';
    }

    /**
     * Obtiene el nombre del servicio o 'Sin servicio'
     */
    public function getServiceName(): string
    {
        return $this->service?->getName() ?? 'Sin servicio';
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setAppointment($this);
        }
        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getAppointment() === $this) {
                $notification->setAppointment(null);
            }
        }
        return $this;
    }

    /**
     * Obtiene las notificaciones pendientes
     */
    public function getPendingNotifications(): Collection
    {
        return $this->notifications->filter(fn(Notification $notification) => $notification->isPending());
    }

    /**
     * Obtiene las notificaciones enviadas
     */
    public function getSentNotifications(): Collection
    {
        return $this->notifications->filter(fn(Notification $notification) => $notification->isSent());
    }

    public function getFeedback(): ?Feedback
    {
        return $this->feedback;
    }

    public function setFeedback(?Feedback $feedback): static
    {
        // unset the owning side of the relation if necessary
        if ($feedback === null && $this->feedback !== null) {
            $this->feedback->setAppointment(null);
        }

        // set the owning side of the relation if necessary
        if ($feedback !== null && $feedback->getAppointment() !== $this) {
            $feedback->setAppointment($this);
        }

        $this->feedback = $feedback;
        return $this;
    }

    /**
     * Verifica si la cita tiene feedback
     */
    public function hasFeedback(): bool
    {
        return $this->feedback !== null;
    }

    /**
     * Verifica si la cita puede recibir feedback
     */
    public function canReceiveFeedback(): bool
    {
        return $this->status === StatusEnum::COMPLETED && !$this->hasFeedback();
    }
}
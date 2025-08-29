<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'professionals')]
class Professional
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Clinic::class, inversedBy: 'professionals')]
    #[ORM\JoinColumn(name: 'clinic_id', referencedColumnName: 'id', nullable: false)]
    private Clinic $clinic;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $specialty = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    // NUEVO CAMPO
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $onlineBookingEnabled = true;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    // NUEVA RELACIÓN
    #[ORM\OneToMany(mappedBy: 'professional', targetEntity: ProfessionalService::class, cascade: ['persist', 'remove'])]
    private Collection $professionalServices;

    // NUEVA RELACIÓN
    #[ORM\OneToMany(mappedBy: 'professional', targetEntity: ProfessionalAvailability::class, cascade: ['persist', 'remove'])]
    private Collection $availabilities;

    // NUEVA RELACIÓN - APPOINTMENTS
    #[ORM\OneToMany(mappedBy: 'professional', targetEntity: Appointment::class, cascade: ['persist', 'remove'])]
    private Collection $appointments;

    // NUEVA RELACIÓN - SCHEDULE BLOCKS
    #[ORM\OneToMany(mappedBy: 'professional', targetEntity: ScheduleBlock::class, cascade: ['persist', 'remove'])]
    private Collection $scheduleBlocks;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->professionalServices = new ArrayCollection();
        $this->availabilities = new ArrayCollection();
        $this->appointments = new ArrayCollection();
        $this->scheduleBlocks = new ArrayCollection(); // NUEVO
    }

    // Getters y Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClinic(): Clinic
    {
        return $this->clinic;
    }

    public function setClinic(Clinic $clinic): self
    {
        $this->clinic = $clinic;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSpecialty(): ?string
    {
        return $this->specialty;
    }

    public function setSpecialty(?string $specialty): self
    {
        $this->specialty = $specialty;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    // NUEVO MÉTODO PARA ONLINE BOOKING
    public function isOnlineBookingEnabled(): bool
    {
        return $this->onlineBookingEnabled;
    }

    public function setOnlineBookingEnabled(bool $onlineBookingEnabled): self
    {
        $this->onlineBookingEnabled = $onlineBookingEnabled;
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

    // NUEVOS MÉTODOS PARA LA RELACIÓN
    /**
     * @return Collection<int, ProfessionalService>
     */
    public function getProfessionalServices(): Collection
    {
        return $this->professionalServices;
    }

    public function addProfessionalService(ProfessionalService $professionalService): static
    {
        if (!$this->professionalServices->contains($professionalService)) {
            $this->professionalServices->add($professionalService);
            $professionalService->setProfessional($this);
        }

        return $this;
    }

    public function removeProfessionalService(ProfessionalService $professionalService): static
    {
        if ($this->professionalServices->removeElement($professionalService)) {
            if ($professionalService->getProfessional() === $this) {
                $professionalService->setProfessional(null);
            }
        }

        return $this;
    }

    /**
     * Obtiene todos los servicios que ofrece este profesional
     */
    public function getServices(): Collection
    {
        return $this->professionalServices->map(fn($ps) => $ps->getService());
    }

    /**
     * Verifica si el profesional ofrece un servicio específico
     */
    public function offersService(Service $service): bool
    {
        return $this->professionalServices->exists(
            fn($key, $ps) => $ps->getService() === $service
        );
    }

    // NUEVOS MÉTODOS PARA APPOINTMENTS
    /**
     * @return Collection<int, Appointment>
     */
    public function getAppointments(): Collection
    {
        return $this->appointments;
    }

    public function addAppointment(Appointment $appointment): self
    {
        if (!$this->appointments->contains($appointment)) {
            $this->appointments->add($appointment);
            $appointment->setProfessional($this);
        }

        return $this;
    }

    public function removeAppointment(Appointment $appointment): self
    {
        if ($this->appointments->removeElement($appointment)) {
            if ($appointment->getProfessional() === $this) {
                $appointment->setProfessional(null);
            }
        }

        return $this;
    }

    // NUEVOS MÉTODOS PARA AVAILABILITIES
    /**
     * @return Collection<int, ProfessionalAvailability>
     */
    public function getAvailabilities(): Collection
    {
        return $this->availabilities;
    }

    public function addAvailability(ProfessionalAvailability $availability): self
    {
        if (!$this->availabilities->contains($availability)) {
            $this->availabilities->add($availability);
            $availability->setProfessional($this);
        }

        return $this;
    }

    public function removeAvailability(ProfessionalAvailability $availability): self
    {
        if ($this->availabilities->removeElement($availability)) {
            if ($availability->getProfessional() === $this) {
                $availability->setProfessional(null);
            }
        }

        return $this;
    }

    /**
     * Obtiene las disponibilidades para un día específico
     */
    public function getAvailabilitiesForWeekday(int $weekday): Collection
    {
        return $this->availabilities->filter(
            fn(ProfessionalAvailability $availability) => $availability->getWeekday() === $weekday
        );
    }

    /**
     * Verifica si el profesional está disponible en un día y hora específicos
     */
    public function isAvailableAt(int $weekday, \DateTimeInterface $time): bool
    {
        $dayAvailabilities = $this->getAvailabilitiesForWeekday($weekday);
        
        foreach ($dayAvailabilities as $availability) {
            if ($availability->isTimeInRange($time)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * @return Collection<int, ScheduleBlock>
     */
    public function getScheduleBlocks(): Collection
    {
        return $this->scheduleBlocks;
    }

    public function addScheduleBlock(ScheduleBlock $scheduleBlock): self
    {
        if (!$this->scheduleBlocks->contains($scheduleBlock)) {
            $this->scheduleBlocks->add($scheduleBlock);
            $scheduleBlock->setProfessional($this);
        }

        return $this;
    }

    public function removeScheduleBlock(ScheduleBlock $scheduleBlock): self
    {
        if ($this->scheduleBlocks->removeElement($scheduleBlock)) {
            if ($scheduleBlock->getProfessional() === $this) {
                $scheduleBlock->setProfessional(null);
            }
        }

        return $this;
    }

    /**
     * Verifica si hay algún bloque activo en una fecha/hora específica
     */
    public function hasActiveBlockAt(\DateTimeInterface $datetime): bool
    {
        return $this->scheduleBlocks->exists(
            fn($key, ScheduleBlock $block) => $block->isActiveAt($datetime)
        );
    }
}
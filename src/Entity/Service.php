<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'services')]
class Service
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // CAMBIO: De Location a Company
    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'services')]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false)]
    private Company $company;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer')]
    private int $defaultDurationMinutes;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    // NUEVOS CAMPOS
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $onlineBookingEnabled = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $showPriceOnBooking = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $showDurationOnBooking = true;
    
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reminderNote = null;

    #[ORM\Column(type: 'string', enumType: DeliveryTypeEnum::class, options: ['default' => 'in_person'])]
    private DeliveryTypeEnum $deliveryType = DeliveryTypeEnum::IN_PERSON;

    #[ORM\Column(type: 'string', enumType: ServiceTypeEnum::class, options: ['default' => 'regular'])]
    private ServiceTypeEnum $serviceType = ServiceTypeEnum::REGULAR;

    // NUEVOS CAMPOS DE IMAGEN
    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $imageUrl1 = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $imageUrl2 = null;

    // NUEVO CAMPO PARA FRECUENCIA
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $frequencyWeeks = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    // NUEVA RELACIÓN
    #[ORM\OneToMany(mappedBy: 'service', targetEntity: ProfessionalService::class, cascade: ['persist', 'remove'])]
    private Collection $professionalServices;

    // NUEVA RELACIÓN
    #[ORM\OneToMany(mappedBy: 'service', targetEntity: Appointment::class)]
    private Collection $appointments;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->professionalServices = new ArrayCollection();
        $this->appointments = new ArrayCollection(); // NUEVO
    }

    // Getters y Setters
    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDefaultDurationMinutes(): int
    {
        return $this->defaultDurationMinutes;
    }

    public function setDefaultDurationMinutes(int $defaultDurationMinutes): self
    {
        $this->defaultDurationMinutes = $defaultDurationMinutes;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPriceFromFloat(?float $price): self
    {
        $this->price = $price !== null ? (string) $price : null;
        return $this;
    }

    public function setPriceFromString(?string $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getPriceAsFloat(): ?float
    {
        return $this->price !== null ? (float) $this->price : null;
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
            $professionalService->setService($this);
        }

        return $this;
    }

    public function removeProfessionalService(ProfessionalService $professionalService): static
    {
        if ($this->professionalServices->removeElement($professionalService)) {
            if ($professionalService->getService() === $this) {
                $professionalService->setService(null);
            }
        }

        return $this;
    }

    /**
     * Obtiene todos los profesionales que ofrecen este servicio
     */
    public function getProfessionals(): Collection
    {
        return $this->professionalServices->map(fn($ps) => $ps->getProfessional());
    }

    /**
     * Verifica si un profesional específico ofrece este servicio
     */
    public function isOfferedBy(Professional $professional): bool
    {
        return $this->professionalServices->exists(
            fn($key, $ps) => $ps->getProfessional() === $professional
        );
    }

    // Método actualizado para usar el nombre correcto
    public function getDurationMinutes(): int
    {
        return $this->defaultDurationMinutes;
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
            $appointment->setService($this);
        }

        return $this;
    }

    public function removeAppointment(Appointment $appointment): self
    {
        if ($this->appointments->removeElement($appointment)) {
            if ($appointment->getService() === $this) {
                $appointment->setService(null);
            }
        }

        return $this;
    }

    // NUEVOS GETTERS Y SETTERS
    public function isOnlineBookingEnabled(): bool
    {
        return $this->onlineBookingEnabled;
    }

    public function setOnlineBookingEnabled(bool $onlineBookingEnabled): self
    {
        $this->onlineBookingEnabled = $onlineBookingEnabled;
        return $this;
    }

    /**
     * Verifica si debe mostrar el precio en la página de reservas
     */
    public function shouldShowPriceOnBooking(): bool
    {
        return $this->onlineBookingEnabled && $this->showPriceOnBooking;
    }

    /**
     * Verifica si debe mostrar la duración en la página de reservas
     */
    public function shouldShowDurationOnBooking(): bool
    {
        return $this->onlineBookingEnabled && $this->showDurationOnBooking;
    }

    public function getShowPriceOnBooking(): bool
    {
        return $this->showPriceOnBooking;
    }

    public function setShowPriceOnBooking(bool $showPriceOnBooking): self
    {
        $this->showPriceOnBooking = $showPriceOnBooking;
        return $this;
    }

    public function getShowDurationOnBooking(): bool
    {
        return $this->showDurationOnBooking;
    }

    public function setShowDurationOnBooking(bool $showDurationOnBooking): self
    {
        $this->showDurationOnBooking = $showDurationOnBooking;
        return $this;
    }

    public function getReminderNote(): ?string
    {
        return $this->reminderNote;
    }

    public function setReminderNote(?string $reminderNote): self
    {
        $this->reminderNote = $reminderNote;
        return $this;
    }

    public function getDeliveryType(): DeliveryTypeEnum
    {
        return $this->deliveryType;
    }

    public function setDeliveryType(DeliveryTypeEnum $deliveryType): self
    {
        $this->deliveryType = $deliveryType;
        return $this;
    }

    public function getServiceType(): ServiceTypeEnum
    {
        return $this->serviceType;
    }

    public function setServiceType(ServiceTypeEnum $serviceType): self
    {
        $this->serviceType = $serviceType;
        return $this;
    }

    public function getFrequencyWeeks(): ?int
    {
        return $this->frequencyWeeks;
    }

    public function setFrequencyWeeks(?int $frequencyWeeks): self
    {
        $this->frequencyWeeks = $frequencyWeeks;
        return $this;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): self
    {
        $this->company = $company;
        return $this;
    }

    // NUEVOS MÉTODOS PARA IMÁGENES
    public function getImageUrl1(): ?string
    {
        return $this->imageUrl1;
    }

    public function setImageUrl1(?string $imageUrl1): self
    {
        $this->imageUrl1 = $imageUrl1;
        return $this;
    }

    public function getImageUrl2(): ?string
    {
        return $this->imageUrl2;
    }

    public function setImageUrl2(?string $imageUrl2): self
    {
        $this->imageUrl2 = $imageUrl2;
        return $this;
    }
}
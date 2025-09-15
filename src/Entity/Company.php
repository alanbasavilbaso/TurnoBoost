<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'companies')]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: Location::class, cascade: ['persist', 'remove'])]
    private Collection $locations;

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: Professional::class, cascade: ['persist', 'remove'])]
    private Collection $professionals;

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: Service::class, cascade: ['persist', 'remove'])]
    private Collection $services;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: User::class)]
    private Collection $users;

    /**
     * Dominio único para acceso público a reservas online.
     * Este será usado como URL: localhost/{domain}
     * Ejemplo: 'beauty-salon' permitirá acceso en localhost/beauty-salon
     * Solo se permiten letras minúsculas, números y guiones.
     */
    #[ORM\Column(type: 'string', length: 100, unique: true)]
    #[Assert\NotBlank(message: 'El dominio es obligatorio')]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'El dominio debe tener al menos {{ limit }} caracteres',
        maxMessage: 'El dominio no puede exceder {{ limit }} caracteres'
    )]
    #[Assert\Regex(
        pattern: '/^[a-z0-9-]+$/',
        message: 'El dominio solo puede contener letras minúsculas, números y guiones'
    )]
    private string $domain;

    /**
     * Tiempo mínimo en minutos que debe pasar desde ahora para poder reservar una cita
     */
    #[ORM\Column(type: 'integer', options: ['default' => 60])]
    #[Assert\NotBlank(message: 'El tiempo mínimo de reserva es obligatorio')]
    #[Assert\Range(
        min: 0,
        max: 10080,
        notInRangeMessage: 'El tiempo mínimo debe estar entre {{ min }} y {{ max }} minutos'
    )]
    private int $minimumBookingTime = 60;

    /**
     * Tiempo máximo en días hacia el futuro que se pueden hacer reservas
     */
    #[ORM\Column(type: 'integer', options: ['default' => 90])]
    #[Assert\NotBlank(message: 'El tiempo máximo de reserva es obligatorio')]
    #[Assert\Range(
        min: 1,
        max: 365,
        notInRangeMessage: 'El tiempo máximo debe estar entre {{ min }} y {{ max }} días'
    )]
    private int $maximumFutureTime = 90;

    /**
     * Permite que los clientes cancelen sus reservas
     */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $cancellableBookings = true;

    /**
     * Permite que los clientes editen sus reservas
     */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $editableBookings = true;

    /**
     * Tiempo mínimo en minutos antes de la cita para poder editarla o cancelarla
     */
    #[ORM\Column(type: 'integer', options: ['default' => 120])]
    #[Assert\NotBlank(message: 'El tiempo mínimo para editar es obligatorio')]
    #[Assert\Range(
        min: 0,
        max: 10080,
        notInRangeMessage: 'El tiempo mínimo para editar debe estar entre {{ min }} y {{ max }} minutos'
    )]
    private int $minimumEditTime = 120;

    /**
     * Número máximo de veces que un cliente puede editar su cita
     */
    #[ORM\Column(type: 'integer', options: ['default' => 3])]
    #[Assert\NotBlank(message: 'El máximo de ediciones es obligatorio')]
    #[Assert\Range(
        min: 0,
        max: 10,
        notInRangeMessage: 'El máximo de ediciones debe estar entre {{ min }} y {{ max }}'
    )]
    private int $maximumEdits = 3;

    /**
     * Habilita las reservas en línea a través del sitio web
     */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $onlineBookingEnabled = true;

    /**
     * Requiere que el cliente tenga datos de contacto (email o teléfono) para crear reservas
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $requireContactData = false;

    /**
     * Nivel de limitación de citas: 'company', 'location', 'professional'
     */
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'company'])]
    #[Assert\Choice(
        choices: ['company', 'location', 'professional'],
        message: 'El nivel de limitación debe ser: company, location o professional'
    )]
    private string $bookingLimitLevel = 'company';

    /**
     * Cantidad máxima de turnos pendientes que puede tener un cliente
     */
    #[ORM\Column(type: 'integer', options: ['default' => 5])]
    #[Assert\NotBlank(message: 'La cantidad máxima de turnos es obligatoria')]
    #[Assert\Range(
        min: 1,
        max: 50,
        notInRangeMessage: 'La cantidad máxima debe estar entre {{ min }} y {{ max }} turnos'
    )]
    private int $maxPendingBookings = 5;

    /**
     * Habilita los pagos en línea a través de Mercado Pago
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $onlinePaymentsEnabled = false;

    
    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->locations = new ArrayCollection();
        $this->professionals = new ArrayCollection();
        $this->services = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    // Getters y setters
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



    public function getMinimumBookingTime(): int
    {
        return $this->minimumBookingTime;
    }

    public function setMinimumBookingTime(int $minimumBookingTime): self
    {
        $this->minimumBookingTime = $minimumBookingTime;
        return $this;
    }

    public function getMaximumFutureTime(): int
    {
        return $this->maximumFutureTime;
    }

    public function setMaximumFutureTime(int $maximumFutureTime): self
    {
        $this->maximumFutureTime = $maximumFutureTime;
        return $this;
    }

    public function isCancellableBookings(): bool
    {
        return $this->cancellableBookings;
    }

    public function setCancellableBookings(bool $cancellableBookings): self
    {
        $this->cancellableBookings = $cancellableBookings;
        return $this;
    }

    public function isEditableBookings(): bool
    {
        return $this->editableBookings;
    }

    public function setEditableBookings(bool $editableBookings): self
    {
        $this->editableBookings = $editableBookings;
        return $this;
    }

    public function getMinimumEditTime(): int
    {
        return $this->minimumEditTime;
    }

    public function setMinimumEditTime(int $minimumEditTime): self
    {
        $this->minimumEditTime = $minimumEditTime;
        return $this;
    }

    public function getMaximumEdits(): int
    {
        return $this->maximumEdits;
    }

    public function setMaximumEdits(int $maximumEdits): self
    {
        $this->maximumEdits = $maximumEdits;
        return $this;
    }

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
     * Verifica si una fecha de reserva está dentro del tiempo mínimo permitido
     */
    public function isWithinMinimumTime(\DateTimeInterface $appointmentDate): bool
    {
        $now = new \DateTime();
        $minimumDateTime = $now->add(new \DateInterval('PT' . $this->minimumBookingTime . 'M'));
        
        return $appointmentDate >= $minimumDateTime;
    }

    /**
     * Verifica si una fecha de reserva está dentro del tiempo máximo permitido
     */
    public function isWithinMaximumTime(\DateTimeInterface $appointmentDate): bool
    {
        $now = new \DateTime();
        $maximumDateTime = $now->add(new \DateInterval('P' . $this->maximumFutureTime . 'D'));
        
        return $appointmentDate <= $maximumDateTime;
    }

    /**
     * Valida si una fecha de reserva cumple con ambos límites
     */
    public function isValidBookingDate(\DateTimeInterface $appointmentDate): bool
    {
        return $this->isWithinMinimumTime($appointmentDate) && $this->isWithinMaximumTime($appointmentDate);
    }

    /**
     * Verifica si una cita puede ser editada basándose en el tiempo mínimo de edición
     */
    public function canEditAppointment(\DateTimeInterface $appointmentDate): bool
    {
        if (!$this->editableBookings) {
            return false;
        }
        
        $now = new \DateTime();
        $minimumEditDateTime = $now->add(new \DateInterval('PT' . $this->minimumEditTime . 'M'));
        
        return $appointmentDate >= $minimumEditDateTime;
    }

    /**
     * Verifica si una cita puede ser cancelada basándose en el tiempo mínimo de edición
     */
    public function canCancelAppointment(\DateTimeInterface $appointmentDate): bool
    {
        if (!$this->cancellableBookings) {
            return false;
        }
        
        $now = new \DateTime();
        $minimumEditDateTime = $now->add(new \DateInterval('PT' . $this->minimumEditTime . 'M'));
        
        return $appointmentDate >= $minimumEditDateTime;
    }

    /**
     * Verifica si se ha alcanzado el máximo número de ediciones permitidas
     */
    public function hasReachedMaximumEdits(int $currentEdits): bool
    {
        return $currentEdits >= $this->maximumEdits;
    }
    
    // Métodos para la colección de locations
    /**
     * @return Collection<int, Location>
     */
    public function getLocations(): Collection
    {
        return $this->locations;
    }

    public function addLocation(Location $location): self
    {
        if (!$this->locations->contains($location)) {
            $this->locations->add($location);
            $location->setCompany($this);
        }

        return $this;
    }

    public function removeLocation(Location $location): self
    {
        if ($this->locations->removeElement($location)) {
            if ($location->getCompany() === $this) {
                $location->setCompany(null);
            }
        }

        return $this;
    }

    // Métodos para la colección de professionals
    /**
     * @return Collection<int, Professional>
     */
    public function getProfessionals(): Collection
    {
        return $this->professionals;
    }

    public function addProfessional(Professional $professional): self
    {
        if (!$this->professionals->contains($professional)) {
            $this->professionals->add($professional);
            $professional->setCompany($this);
        }

        return $this;
    }

    public function removeProfessional(Professional $professional): self
    {
        if ($this->professionals->removeElement($professional)) {
            if ($professional->getCompany() === $this) {
                $professional->setCompany(null);
            }
        }

        return $this;
    }

    // Métodos para la colección de services
    /**
     * @return Collection<int, Service>
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(Service $service): self
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
            $service->setCompany($this);
        }

        return $this;
    }

    public function removeService(Service $service): self
    {
        if ($this->services->removeElement($service)) {
            if ($service->getCompany() === $this) {
                // No podemos setear null porque company es required
                // El service debe ser transferido a otra company
            }
        }
        return $this;
    }


    public function getActiveProfessionals(): Collection
    {
        return $this->professionals->filter(fn(Professional $professional) => $professional->isActive());
    }

    public function getActiveServices(): Collection
    {
        return $this->services->filter(fn(Service $service) => $service->isActive());
    }

    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setCompany($this);
        }
        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->removeElement($user)) {
            if ($user->getCompany() === $this) {
                $user->setCompany(null);
            }
        }
        return $this;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = strtolower(trim($domain));
        return $this;
    }
    
    public function getBookingUrl(): string
    {
        return '/reservas/' . $this->domain;
    }
    
    public function getRandomDomain(): string
    {
        return $this->generateRandomDomainPart();
    }

    private function generateRandomDomainPart(int $length = 15): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $randomString;
    }

    // Getters y setters para los nuevos campos

    public function isRequireContactData(): bool
    {
        return $this->requireContactData;
    }

    public function setRequireContactData(bool $requireContactData): self
    {
        $this->requireContactData = $requireContactData;
        return $this;
    }

    public function getBookingLimitLevel(): string
    {
        return $this->bookingLimitLevel;
    }

    public function setBookingLimitLevel(string $bookingLimitLevel): self
    {
        $this->bookingLimitLevel = $bookingLimitLevel;
        return $this;
    }

    public function getMaxPendingBookings(): int
    {
        return $this->maxPendingBookings;
    }

    public function setMaxPendingBookings(int $maxPendingBookings): self
    {
        $this->maxPendingBookings = $maxPendingBookings;
        return $this;
    }

    public function isOnlinePaymentsEnabled(): bool
    {
        return $this->onlinePaymentsEnabled;
    }

    public function setOnlinePaymentsEnabled(bool $onlinePaymentsEnabled): self
    {
        $this->onlinePaymentsEnabled = $onlinePaymentsEnabled;
        return $this;
    }

    // Métodos de utilidad para los nuevos campos

    /**
     * Verifica si un cliente puede crear una nueva reserva basado en el límite configurado
     */
    public function canClientCreateBooking(int $currentPendingBookings): bool
    {
        return $currentPendingBookings < $this->maxPendingBookings;
    }

    /**
     * Obtiene las opciones disponibles para el nivel de limitación de reservas
     */
    public static function getBookingLimitLevelOptions(): array
    {
        return [
            'company' => 'Por Empresa',
            'location' => 'Por Local',
            'professional' => 'Por Profesional'
        ];
    }
}

<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity]
#[ORM\Table(name: 'locations')]
class Location
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'El nombre de la ubicación es obligatorio')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'El nombre debe tener al menos {{ limit }} caracteres',
        maxMessage: 'El nombre no puede exceder {{ limit }} caracteres'
    )]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'La dirección es obligatoria')]
    #[Assert\Length(
        min: 5,
        max: 500,
        minMessage: 'La dirección debe tener al menos {{ limit }} caracteres',
        maxMessage: 'La dirección no puede exceder {{ limit }} caracteres'
    )]
    private ?string $address = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\NotBlank(message: 'El teléfono es obligatorio')]
    #[Assert\Length(
        min: 8,
        max: 20,
        minMessage: 'El teléfono debe tener al menos {{ limit }} dígitos',
        maxMessage: 'El teléfono no puede exceder {{ limit }} caracteres'
    )]
    private ?string $phone = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'El email es obligatorio')]
    #[Assert\Email(message: 'Por favor ingrese un email válido')]
    private ?string $email = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'locations')]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false)]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(mappedBy: 'location', targetEntity: LocationAvailability::class, cascade: ['persist', 'remove'])]
    private Collection $availabilities;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->availabilities = new ArrayCollection();
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
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

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): self
    {
        $this->company = $company;
        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->createdBy->getId() === $user->getId();
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

    /**
     * @return Collection<int, LocationAvailability>
     */
    public function getAvailabilities(): Collection
    {
        return $this->availabilities;
    }

    public function addAvailability(LocationAvailability $availability): self
    {
        if (!$this->availabilities->contains($availability)) {
            $this->availabilities->add($availability);
            $availability->setLocation($this);
        }
        return $this;
    }

    public function removeAvailability(LocationAvailability $availability): self
    {
        if ($this->availabilities->removeElement($availability)) {
            // No llamar a setLocation(null) ya que no acepta null
            // La relación se manejará automáticamente por Doctrine
        }
        return $this;
    }

    /**
     * Obtiene las disponibilidades para un día específico
     */
    public function getAvailabilitiesForWeekDay(int $weekDay): Collection
    {
        return $this->availabilities->filter(
            fn(LocationAvailability $availability) => $availability->getWeekDay() === $weekDay
        );
    }

    /**
     * Verifica si la ubicación está disponible en un día y hora específicos
     */
    public function isAvailableAt(int $weekDay, \DateTimeInterface $time): bool
    {
        $dayAvailabilities = $this->getAvailabilitiesForWeekDay($weekDay);
        
        foreach ($dayAvailabilities as $availability) {
            if ($availability->isTimeInRange($time)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Obtiene todos los horarios formateados para mostrar
     */
    public function getFormattedSchedules(): array
    {
        $schedules = [];
        foreach ($this->availabilities as $availability) {
            $schedules[] = $availability->getFormattedSchedule();
        }
        return $schedules;
    }
}
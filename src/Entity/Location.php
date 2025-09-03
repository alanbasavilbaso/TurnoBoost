<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

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
        min: 10,
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ownedLocations')]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(mappedBy: 'location', targetEntity: User::class)]
    private Collection $users;

    #[ORM\OneToMany(mappedBy: 'location', targetEntity: Professional::class)]
    private Collection $professionals;

    #[ORM\OneToMany(mappedBy: 'location', targetEntity: Patient::class)]
    private Collection $patients;

    #[ORM\OneToMany(mappedBy: 'location', targetEntity: Service::class)]
    private Collection $services;

    #[ORM\OneToMany(mappedBy: 'location', targetEntity: Appointment::class)]
    private Collection $appointments;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->users = new ArrayCollection();
        $this->professionals = new ArrayCollection();
        $this->patients = new ArrayCollection();
        $this->services = new ArrayCollection();
        $this->appointments = new ArrayCollection();
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

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setLocation($this);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->removeElement($user)) {
            if ($user->getLocation() === $this) {
                $user->setLocation(null);
            }
        }

        return $this;
    }

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
            $professional->setLocation($this);
        }

        return $this;
    }

    public function removeProfessional(Professional $professional): self
    {
        if ($this->professionals->removeElement($professional)) {
            if ($professional->getLocation() === $this) {
                $professional->setLocation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Patient>
     */
    public function getPatients(): Collection
    {
        return $this->patients;
    }

    public function addPatient(Patient $patient): self
    {
        if (!$this->patients->contains($patient)) {
            $this->patients->add($patient);
            $patient->setLocation($this);
        }

        return $this;
    }

    public function removePatient(Patient $patient): self
    {
        if ($this->patients->removeElement($patient)) {
            if ($patient->getLocation() === $this) {
                $patient->setLocation(null);
            }
        }

        return $this;
    }

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
            $service->setLocation($this);
        }

        return $this;
    }

    public function removeService(Service $service): self
    {
        if ($this->services->removeElement($service)) {
            if ($service->getLocation() === $this) {
                $service->setLocation(null);
            }
        }

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
            $appointment->setLocation($this);
        }

        return $this;
    }

    public function removeAppointment(Appointment $appointment): self
    {
        if ($this->appointments->removeElement($appointment)) {
            if ($appointment->getLocation() === $this) {
                $appointment->setLocation(null);
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

    public function getRandomDomain(): string
    {
        return $this->generateRandomDomainPart();
    }

    private function generateRandomDomainPart(int $length = 15): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $charactersLength = strlen($characters);

        // Generar cadena continua
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }

        // Dividir en partes de 5 y unir con "-"
        return implode('-', str_split($randomString, 5));
    }

    public function getBookingUrl(): string
    {
        return '/reservas/' . $this->domain;
    }
}
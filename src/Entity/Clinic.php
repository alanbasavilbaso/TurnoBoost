<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity]
#[ORM\Table(name: 'clinics')]
class Clinic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'El nombre de la clínica es obligatorio')]
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

    // NUEVA RELACIÓN: Usuario que creó la clínica
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ownedClinics')]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(mappedBy: 'clinic', targetEntity: User::class)]
    private Collection $users;

    #[ORM\OneToMany(mappedBy: 'clinic', targetEntity: Professional::class)]
    private Collection $professionals;

    #[ORM\OneToMany(mappedBy: 'clinic', targetEntity: Patient::class)]
    private Collection $patients;

    #[ORM\OneToMany(mappedBy: 'clinic', targetEntity: Service::class)]
    private Collection $services;

    // NUEVA RELACIÓN
    #[ORM\OneToMany(mappedBy: 'clinic', targetEntity: Appointment::class)]
    private Collection $appointments;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->users = new ArrayCollection();
        $this->professionals = new ArrayCollection();
        $this->patients = new ArrayCollection();
        $this->services = new ArrayCollection();
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
            $user->setClinic($this);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getClinic() === $this) {
                $user->setClinic(null);
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
            $professional->setClinic($this);
        }

        return $this;
    }

    public function removeProfessional(Professional $professional): self
    {
        if ($this->professionals->removeElement($professional)) {
            // set the owning side to null (unless already changed)
            if ($professional->getClinic() === $this) {
                $professional->setClinic(null);
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
            $patient->setClinic($this);
        }

        return $this;
    }

    public function removePatient(Patient $patient): self
    {
        if ($this->patients->removeElement($patient)) {
            // set the owning side to null (unless already changed)
            if ($patient->getClinic() === $this) {
                $patient->setClinic(null);
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
            $service->setClinic($this);
        }

        return $this;
    }

    public function removeService(Service $service): self
    {
        if ($this->services->removeElement($service)) {
            // set the owning side to null (unless already changed)
            if ($service->getClinic() === $this) {
                $service->setClinic(null);
            }
        }

        return $this;
    }

    // NUEVOS MÉTODOS PARA createdBy
    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    // Método helper para verificar si un usuario puede editar esta clínica
    public function canBeEditedBy(User $user): bool
    {
        return $this->createdBy->getId() === $user->getId();
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
            $appointment->setClinic($this);
        }

        return $this;
    }

    public function removeAppointment(Appointment $appointment): self
    {
        if ($this->appointments->removeElement($appointment)) {
            if ($appointment->getClinic() === $this) {
                $appointment->setClinic(null);
            }
        }

        return $this;
    }

    /**
     * Obtiene el dominio de la clínica para acceso público
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Establece el dominio de la clínica (se convierte automáticamente a minúsculas)
     */
    public function setDomain(string $domain): self
    {
        $this->domain = strtolower(trim($domain));
        return $this;
    }

    /**
     * Genera la URL pública de reservas para esta clínica
     */
    public function getBookingUrl(): string
    {
        return '/reservas/' . $this->domain;
    }
}

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

    #[ORM\OneToOne(mappedBy: 'company', targetEntity: Settings::class, cascade: ['persist', 'remove'])]
    private ?Settings $settings = null;

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

     public function getSettings(): ?Settings
    {
        return $this->settings;
    }

    public function setSettings(?Settings $settings): self
    {
        $this->settings = $settings;
        return $this;
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
}

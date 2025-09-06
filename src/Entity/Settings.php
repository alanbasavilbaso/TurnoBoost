<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'settings')]
class Settings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: 'El tiempo mínimo de reserva es requerido')]
    #[Assert\GreaterThan(value: 0, message: 'El tiempo mínimo debe ser mayor a 0')]
    private int $minimumBookingTime = 60; // minutos

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: 'El tiempo máximo futuro es requerido')]
    #[Assert\GreaterThan(value: 0, message: 'El tiempo máximo debe ser mayor a 0')]
    private int $maximumFutureTime = 13; // meses

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getMinimumBookingTime(): int
    {
        return $this->minimumBookingTime;
    }

    public function setMinimumBookingTime(int $minimumBookingTime): self
    {
        $this->minimumBookingTime = $minimumBookingTime;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getMaximumFutureTime(): int
    {
        return $this->maximumFutureTime;
    }

    public function setMaximumFutureTime(int $maximumFutureTime): self
    {
        $this->maximumFutureTime = $maximumFutureTime;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
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
        $maximumDateTime = $now->add(new \DateInterval('P' . $this->maximumFutureTime . 'M'));
        
        return $appointmentDate <= $maximumDateTime;
    }

    /**
     * Valida si una fecha de reserva cumple con ambos límites
     */
    public function isValidBookingDate(\DateTimeInterface $appointmentDate): bool
    {
        return $this->isWithinMinimumTime($appointmentDate) && $this->isWithinMaximumTime($appointmentDate);
    }
}
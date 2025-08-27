<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'feedback')]
class Feedback
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Appointment::class, inversedBy: 'feedback')]
    #[ORM\JoinColumn(name: 'appointment_id', referencedColumnName: 'id', nullable: false, unique: true)]
    private Appointment $appointment;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $rating = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAppointment(): Appointment
    {
        return $this->appointment;
    }

    public function setAppointment(Appointment $appointment): static
    {
        $this->appointment = $appointment;
        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): static
    {
        $this->rating = $rating;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Verifica si el feedback tiene una calificación
     */
    public function hasRating(): bool
    {
        return $this->rating !== null;
    }

    /**
     * Verifica si el feedback tiene comentarios
     */
    public function hasComment(): bool
    {
        return $this->comment !== null && trim($this->comment) !== '';
    }

    /**
     * Verifica si el feedback está completo (tiene rating o comentario)
     */
    public function isComplete(): bool
    {
        return $this->hasRating() || $this->hasComment();
    }

    /**
     * Obtiene una representación textual del rating
     */
    public function getRatingText(): string
    {
        if ($this->rating === null) {
            return 'Sin calificación';
        }

        return match($this->rating) {
            1 => 'Muy malo',
            2 => 'Malo',
            3 => 'Regular',
            4 => 'Bueno',
            5 => 'Excelente',
            default => 'Calificación: ' . $this->rating
        };
    }
}
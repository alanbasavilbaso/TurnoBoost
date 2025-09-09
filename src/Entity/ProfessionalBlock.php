<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'professional_blocks')]
class ProfessionalBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: Professional::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Professional $professional;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\Choice(choices: ['single_day', 'date_range', 'weekdays_pattern', 'monthly_recurring'])]
    private string $blockType;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    private string $reason;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull]
    private \DateTimeInterface $startDate;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endTime = null;

    // Cambiar la validación para usar 0-6 como en ProfessionalAvailability
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^\\d+(,\\d+)*$/', message: 'Los días deben ser números del 0-6 separados por comas')]
    private ?string $weekdaysPattern = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(min: 1, max: 31)]
    private ?int $monthlyDayOfMonth = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $monthlyEndDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $active = true;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // Métodos helper para weekdays_pattern (usando 0-6)
    public function setWeekdaysFromArray(array $weekdays): self
    {
        // Validar que todos los días estén en el rango 0-6
        foreach ($weekdays as $day) {
            if ($day < 0 || $day > 6) {
                throw new \InvalidArgumentException('Weekday must be between 0 (Monday) and 6 (Sunday)');
            }
        }
        $this->weekdaysPattern = implode(',', $weekdays);
        return $this;
    }

    public function getWeekdaysAsArray(): array
    {
        if (!$this->weekdaysPattern) {
            return [];
        }
        return array_map('intval', explode(',', $this->weekdaysPattern));
    }

    public function hasWeekday(int $weekday): bool
    {
        if ($weekday < 0 || $weekday > 6) {
            return false;
        }
        return in_array($weekday, $this->getWeekdaysAsArray());
    }

    public function getWeekdayNames(): array
    {
        $days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        $weekdays = $this->getWeekdaysAsArray();
        return array_map(fn($day) => $days[$day] ?? 'Desconocido', $weekdays);
    }

    // Getters y setters estándar...
    public function getId(): ?int
    {
        return $this->id;
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

    public function getProfessional(): Professional
    {
        return $this->professional;
    }

    public function setProfessional(Professional $professional): self
    {
        $this->professional = $professional;
        return $this;
    }

    public function getBlockType(): string
    {
        return $this->blockType;
    }

    public function setBlockType(string $blockType): self
    {
        $this->blockType = $blockType;
        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    public function getWeekdaysPattern(): ?string
    {
        return $this->weekdaysPattern;
    }

    public function setWeekdaysPattern(?string $weekdaysPattern): self
    {
        $this->weekdaysPattern = $weekdaysPattern;
        return $this;
    }

    public function getMonthlyDayOfMonth(): ?int
    {
        return $this->monthlyDayOfMonth;
    }

    public function setMonthlyDayOfMonth(?int $monthlyDayOfMonth): static
    {
        $this->monthlyDayOfMonth = $monthlyDayOfMonth;
        return $this;
    }

    public function getMonthlyEndDate(): ?\DateTimeInterface
    {
        return $this->monthlyEndDate;
    }

    public function setMonthlyEndDate(?\DateTimeInterface $monthlyEndDate): static
    {
        $this->monthlyEndDate = $monthlyEndDate;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
        return $this;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getStartDate(): \DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;
        return $this;
    }
}
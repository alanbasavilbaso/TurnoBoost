<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250918200246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add confirmedAt and cancelledAt fields to Appointment entity';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appointments ADD confirmed_at TIMESTAMP DEFAULT NULL');
        $this->addSql('ALTER TABLE appointments ADD cancelled_at TIMESTAMP DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appointments DROP confirmed_at');
        $this->addSql('ALTER TABLE appointments DROP cancelled_at');
    }
}

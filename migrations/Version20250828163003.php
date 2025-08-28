<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250828163003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE services ADD online_booking_enabled BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE services ADD reminder_note TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE services ADD delivery_type VARCHAR(255) DEFAULT \'in_person\' NOT NULL');
        $this->addSql('ALTER TABLE services ADD service_type VARCHAR(255) DEFAULT \'regular\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE services DROP online_booking_enabled');
        $this->addSql('ALTER TABLE services DROP reminder_note');
        $this->addSql('ALTER TABLE services DROP delivery_type');
        $this->addSql('ALTER TABLE services DROP service_type');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250915202925 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE companies ADD require_contact_data BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE companies ADD booking_limit_level VARCHAR(20) DEFAULT \'company\' NOT NULL');
        $this->addSql('ALTER TABLE companies ADD max_pending_bookings INT DEFAULT 5 NOT NULL');
        $this->addSql('ALTER TABLE companies ADD online_payments_enabled BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE companies DROP require_contact_data');
        $this->addSql('ALTER TABLE companies DROP booking_limit_level');
        $this->addSql('ALTER TABLE companies DROP max_pending_bookings');
        $this->addSql('ALTER TABLE companies DROP online_payments_enabled');
    }
}

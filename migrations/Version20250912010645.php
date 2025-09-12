<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250912010645 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename special_schedules columns from Spanish to English and update location_availability';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE location_availability ALTER location_id DROP NOT NULL');
        
        // Add new columns as nullable first
        $this->addSql('ALTER TABLE special_schedules ADD start_time TIME(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE special_schedules ADD end_time TIME(0) WITHOUT TIME ZONE');
        
        // Copy data from old columns to new columns
        $this->addSql('UPDATE special_schedules SET start_time = hora_desde WHERE hora_desde IS NOT NULL');
        $this->addSql('UPDATE special_schedules SET end_time = hora_hasta WHERE hora_hasta IS NOT NULL');
        
        // Now make the new columns NOT NULL
        $this->addSql('ALTER TABLE special_schedules ALTER COLUMN start_time SET NOT NULL');
        $this->addSql('ALTER TABLE special_schedules ALTER COLUMN end_time SET NOT NULL');
        
        // Drop old columns
        $this->addSql('ALTER TABLE special_schedules DROP hora_desde');
        $this->addSql('ALTER TABLE special_schedules DROP hora_hasta');
        
        // Rename fecha to date
        $this->addSql('ALTER TABLE special_schedules RENAME COLUMN fecha TO date');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE location_availability ALTER location_id SET NOT NULL');
        
        // Add old columns as nullable first
        $this->addSql('ALTER TABLE special_schedules ADD hora_desde TIME(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE special_schedules ADD hora_hasta TIME(0) WITHOUT TIME ZONE');
        
        // Copy data back from new columns to old columns
        $this->addSql('UPDATE special_schedules SET hora_desde = start_time WHERE start_time IS NOT NULL');
        $this->addSql('UPDATE special_schedules SET hora_hasta = end_time WHERE end_time IS NOT NULL');
        
        // Make old columns NOT NULL
        $this->addSql('ALTER TABLE special_schedules ALTER COLUMN hora_desde SET NOT NULL');
        $this->addSql('ALTER TABLE special_schedules ALTER COLUMN hora_hasta SET NOT NULL');
        
        // Drop new columns
        $this->addSql('ALTER TABLE special_schedules DROP start_time');
        $this->addSql('ALTER TABLE special_schedules DROP end_time');
        
        // Rename date back to fecha
        $this->addSql('ALTER TABLE special_schedules RENAME COLUMN date TO fecha');
    }
}

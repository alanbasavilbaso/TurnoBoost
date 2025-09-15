<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250915185324 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add booking configuration fields to Company table and remove Settings table';
    }

    public function up(Schema $schema): void
    {
        // Add new fields to companies table (PostgreSQL syntax)
        $this->addSql('ALTER TABLE companies ADD minimum_booking_time INT DEFAULT 60 NOT NULL');
        $this->addSql('ALTER TABLE companies ADD maximum_future_time INT DEFAULT 90 NOT NULL');
        
        // Drop settings table if it exists
        $this->addSql('DROP TABLE IF EXISTS settings');
    }

    public function down(Schema $schema): void
    {
        // Remove fields from companies table
        $this->addSql('ALTER TABLE companies DROP COLUMN minimum_booking_time');
        $this->addSql('ALTER TABLE companies DROP COLUMN maximum_future_time');
        
        // Note: We don't recreate the settings table as this functionality 
        // has been permanently moved to the companies table
    }
}

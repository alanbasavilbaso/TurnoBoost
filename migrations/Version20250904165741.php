<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250904165741 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add id_document, last_name, birthdate fields and rename name to first_name in patients table';
    }

    public function up(Schema $schema): void
    {
        // Add new columns (nullable first)
        $this->addSql('ALTER TABLE patients ADD id_document VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE patients ADD last_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE patients ADD birthdate DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE patients RENAME COLUMN name TO first_name');
        
        // Update existing records to have a default last_name
        $this->addSql("UPDATE patients SET last_name = 'Sin especificar' WHERE last_name IS NULL");
        
        // Now make last_name NOT NULL
        $this->addSql('ALTER TABLE patients ALTER COLUMN last_name SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE patients ADD name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE patients DROP id_document');
        $this->addSql('ALTER TABLE patients DROP first_name');
        $this->addSql('ALTER TABLE patients DROP last_name');
        $this->addSql('ALTER TABLE patients DROP birthdate');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250829213742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add domain field to clinics table with random values for existing records';
    }

    public function up(Schema $schema): void
    {
        // Primero agregamos el campo como nullable
        $this->addSql('ALTER TABLE clinics ADD domain VARCHAR(100) DEFAULT NULL');
        
        // Generamos dominios únicos para las clínicas existentes
        $this->addSql("
            UPDATE clinics 
            SET domain = CONCAT('clinic-', LOWER(REPLACE(name, ' ', '-')), '-', EXTRACT(EPOCH FROM NOW())::INTEGER)
            WHERE domain IS NULL
        ");
        
        // Ahora hacemos el campo NOT NULL
        $this->addSql('ALTER TABLE clinics ALTER COLUMN domain SET NOT NULL');
        
        // Creamos el índice único
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D7053B66A7A91E0B ON clinics (domain)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_D7053B66A7A91E0B');
        $this->addSql('ALTER TABLE clinics DROP domain');
    }
}

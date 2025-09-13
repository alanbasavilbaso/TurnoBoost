<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250912124605 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add special_schedule_services table for many-to-many relationship';    
    }

    public function up(Schema $schema): void
    {
        // Crear tabla de relación many-to-many
        $this->addSql('CREATE TABLE special_schedule_services (
            special_schedule_id INT NOT NULL,
            service_id INT NOT NULL,
            PRIMARY KEY(special_schedule_id, service_id)
        )');
        
        $this->addSql('ALTER TABLE special_schedule_services 
            ADD CONSTRAINT FK_SSS_SPECIAL_SCHEDULE 
            FOREIGN KEY (special_schedule_id) 
            REFERENCES special_schedules (id) 
            ON DELETE CASCADE');
            
        $this->addSql('ALTER TABLE special_schedule_services 
            ADD CONSTRAINT FK_SSS_SERVICE 
            FOREIGN KEY (service_id) 
            REFERENCES services (id) 
            ON DELETE CASCADE');
            
        $this->addSql('CREATE INDEX IDX_SSS_SPECIAL_SCHEDULE ON special_schedule_services (special_schedule_id)');
        $this->addSql('CREATE INDEX IDX_SSS_SERVICE ON special_schedule_services (service_id)');

    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE special_schedule_services');
    }
}

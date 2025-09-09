<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250909210138 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create special_schedules table for professional special working hours';
    }

    public function up(Schema $schema): void
    {
        // Crear tabla special_schedules
        $this->addSql('CREATE TABLE special_schedules (
            id SERIAL PRIMARY KEY,
            professional_id INT NOT NULL,
            user_id INT NOT NULL,
            fecha DATE NOT NULL,
            hora_desde TIME NOT NULL,
            hora_hasta TIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        
        // Agregar claves foráneas
        $this->addSql('ALTER TABLE special_schedules ADD CONSTRAINT FK_SS_PROFESSIONAL 
            FOREIGN KEY (professional_id) REFERENCES professionals (id) ON DELETE CASCADE');
        
        $this->addSql('ALTER TABLE special_schedules ADD CONSTRAINT FK_SS_USER 
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        
        // Agregar índices para optimizar consultas
        $this->addSql('CREATE INDEX IDX_SS_PROFESSIONAL_DATE ON special_schedules (professional_id, fecha)');
        $this->addSql('CREATE INDEX IDX_SS_DATE ON special_schedules (fecha)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE special_schedules');
    }
}

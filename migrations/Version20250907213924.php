<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250907213924 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create professional_blocks table for agenda blocking functionality';
    }

    public function up(Schema $schema): void
    {
        // Crear tabla professional_blocks
        $this->addSql('CREATE TABLE professional_blocks (
            id SERIAL PRIMARY KEY,
            company_id INT NOT NULL,
            professional_id INT NOT NULL,
            block_type VARCHAR(20) NOT NULL,
            reason VARCHAR(255) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE DEFAULT NULL,
            start_time TIME DEFAULT NULL,
            end_time TIME DEFAULT NULL,
            weekdays_pattern VARCHAR(20) DEFAULT NULL,
            monthly_day_of_month INT DEFAULT NULL,
            monthly_end_date DATE DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            active BOOLEAN DEFAULT TRUE
        )');
        
        // Agregar claves foráneas
        $this->addSql('ALTER TABLE professional_blocks ADD CONSTRAINT FK_PB_COMPANY 
            FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        
        $this->addSql('ALTER TABLE professional_blocks ADD CONSTRAINT FK_PB_PROFESSIONAL 
            FOREIGN KEY (professional_id) REFERENCES professionals (id) ON DELETE CASCADE');
        
        // Agregar índices para optimizar consultas
        $this->addSql('CREATE INDEX IDX_PB_COMPANY_PROFESSIONAL ON professional_blocks (company_id, professional_id)');
        $this->addSql('CREATE INDEX IDX_PB_DATE_RANGE ON professional_blocks (start_date, end_date)');
        $this->addSql('CREATE INDEX IDX_PB_BLOCK_TYPE ON professional_blocks (block_type)');
        $this->addSql('CREATE INDEX IDX_PB_ACTIVE ON professional_blocks (active)');
        
        // Agregar constraint para validar block_type
        $this->addSql('ALTER TABLE professional_blocks ADD CONSTRAINT CHK_BLOCK_TYPE 
            CHECK (block_type IN (\'single_day\', \'date_range\', \'weekdays_pattern\', \'monthly_recurring\'))');
        
        // Agregar constraint para validar monthly_day_of_month
        $this->addSql('ALTER TABLE professional_blocks ADD CONSTRAINT CHK_MONTHLY_DAY 
            CHECK (monthly_day_of_month IS NULL OR (monthly_day_of_month >= 1 AND monthly_day_of_month <= 31))');

        // Agregar constraint para validar weekdays_pattern (números del 0-6 separados por comas)
        $this->addSql('ALTER TABLE professional_blocks ADD CONSTRAINT CHK_WEEKDAYS_PATTERN 
            CHECK (weekdays_pattern IS NULL OR weekdays_pattern ~ \'^[0-6](,[0-6])*$\')');

        // Crear función para actualizar updated_at automáticamente
        $this->addSql('CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language \'plpgsql\'');
        
        // Crear trigger para updated_at
        $this->addSql('CREATE TRIGGER update_professional_blocks_updated_at 
            BEFORE UPDATE ON professional_blocks 
            FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()');
    }

    public function down(Schema $schema): void
    {
        // Eliminar trigger y función
        $this->addSql('DROP TRIGGER IF EXISTS update_professional_blocks_updated_at ON professional_blocks');
        $this->addSql('DROP FUNCTION IF EXISTS update_updated_at_column()');
        
        // Eliminar tabla
        $this->addSql('DROP TABLE professional_blocks');
    }
}

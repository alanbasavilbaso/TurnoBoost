<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250907194920 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move domain field from locations to companies';
    }

    public function up(Schema $schema): void
    {
        // Paso 1: Agregar columna domain como nullable temporalmente
        $this->addSql('ALTER TABLE companies ADD domain VARCHAR(100) DEFAULT NULL');
        
        // Paso 2: Migrar datos de locations.domain a companies.domain
        // Tomar el primer domain de cada company (asumiendo que todas las locations de una company tienen el mismo domain)
        $this->addSql('
            UPDATE companies 
            SET domain = (
                SELECT l.domain 
                FROM locations l 
                WHERE l.company_id = companies.id 
                AND l.domain IS NOT NULL 
                LIMIT 1
            )
            WHERE EXISTS (
                SELECT 1 FROM locations l2 
                WHERE l2.company_id = companies.id 
                AND l2.domain IS NOT NULL
            )
        ');
        
        // Paso 3: Para companies sin domain, generar uno único
        $this->addSql('
            UPDATE companies 
            SET domain = CONCAT(\'company-\', id, \'-\', EXTRACT(EPOCH FROM NOW())::bigint)
            WHERE domain IS NULL
        ');
        
        // Paso 4: Hacer la columna NOT NULL y agregar índice único
        $this->addSql('ALTER TABLE companies ALTER COLUMN domain SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8244AA3AA7A91E0B ON companies (domain)');
        
        // Paso 5: Eliminar domain de locations
        $this->addSql('DROP INDEX IF EXISTS uniq_17e64abaa7a91e0b');
        $this->addSql('ALTER TABLE locations DROP COLUMN domain');
    }

    public function down(Schema $schema): void
    {
        // Revertir los cambios
        $this->addSql('ALTER TABLE locations ADD domain VARCHAR(100) DEFAULT NULL');
        
        // Migrar domain de vuelta a locations (tomar el domain de la company)
        $this->addSql('
            UPDATE locations 
            SET domain = c.domain 
            FROM companies c 
            WHERE locations.company_id = c.id
        ');
        
        $this->addSql('ALTER TABLE locations ALTER COLUMN domain SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_17e64abaa7a91e0b ON locations (domain)');
        
        $this->addSql('DROP INDEX UNIQ_8244AA3AA7A91E0B');
        $this->addSql('ALTER TABLE companies DROP COLUMN domain');
    }
}

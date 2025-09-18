<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250916213336 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add source field to appointments table to distinguish between admin and user created appointments';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appointments ADD source VARCHAR(255) NOT NULL DEFAULT \'user\'');
        
        // Actualizar las citas existentes para marcarlas como creadas por admin
        // (asumiendo que las existentes fueron creadas desde la agenda)
        $this->addSql('UPDATE appointments SET source = \'admin\' WHERE created_at < NOW()');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appointments DROP source');
    }
}

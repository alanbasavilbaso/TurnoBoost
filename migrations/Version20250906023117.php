<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250906023117 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create settings table for company configuration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE settings (
            id SERIAL NOT NULL, 
            user_id INT NOT NULL, 
            minimum_booking_time INT NOT NULL DEFAULT 60, 
            maximum_future_time INT NOT NULL DEFAULT 13, 
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, 
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, 
            PRIMARY KEY(id)
        )');
        
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E545A0C5A76ED395 ON settings (user_id)');
        $this->addSql('ALTER TABLE settings ADD CONSTRAINT FK_E545A0C5A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE settings DROP CONSTRAINT FK_E545A0C5A76ED395');
        $this->addSql('DROP TABLE settings');
    }
}

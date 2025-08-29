<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250829191328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_log (id SERIAL NOT NULL, user_id INT DEFAULT NULL, entity_type VARCHAR(50) NOT NULL, entity_id INT NOT NULL, action VARCHAR(20) NOT NULL, old_values JSON DEFAULT NULL, new_values JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(500) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F6E1C0F5A76ED395 ON audit_log (user_id)');
        $this->addSql('COMMENT ON COLUMN audit_log.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F5A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE audit_log DROP CONSTRAINT FK_F6E1C0F5A76ED395');
        $this->addSql('DROP TABLE audit_log');
    }
}

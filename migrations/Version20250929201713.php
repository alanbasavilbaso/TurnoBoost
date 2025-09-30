<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250929201713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE whatsapp_api_logs (id SERIAL NOT NULL, endpoint VARCHAR(255) NOT NULL, method VARCHAR(10) NOT NULL, request_payload JSON NOT NULL, response_data JSON DEFAULT NULL, http_status INT DEFAULT NULL, error_message TEXT DEFAULT NULL, phone_number VARCHAR(20) DEFAULT NULL, appointment_id INT DEFAULT NULL, message_type VARCHAR(50) DEFAULT NULL, message_id VARCHAR(100) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, response_time_ms INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE companies ALTER phone TYPE VARCHAR(20)');
        $this->addSql('DROP INDEX idx_patients_deleted_at');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE whatsapp_api_logs');
        $this->addSql('ALTER TABLE companies ALTER phone TYPE VARCHAR(15)');
        $this->addSql('CREATE INDEX idx_patients_deleted_at ON patients (deleted_at)');
    }
}

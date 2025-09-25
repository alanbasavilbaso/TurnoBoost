<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250924173149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_6a41727a_modification_count');
        $this->addSql('ALTER TABLE appointments ALTER source DROP DEFAULT');
        $this->addSql('ALTER INDEX idx_6a41727a_original RENAME TO IDX_6A41727A1EAE1E45');
        $this->addSql('ALTER INDEX idx_6a41727a_previous RENAME TO IDX_6A41727A38B08FD8');
        $this->addSql('ALTER TABLE companies ADD phone VARCHAR(15) DEFAULT NULL');
        $this->addSql('ALTER TABLE companies ADD whatsapp_connection_status VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE companies ADD whatsapp_last_checked TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE appointments ALTER source SET DEFAULT \'user\'');
        $this->addSql('CREATE INDEX idx_6a41727a_modification_count ON appointments (modification_count)');
        $this->addSql('ALTER INDEX idx_6a41727a38b08fd8 RENAME TO idx_6a41727a_previous');
        $this->addSql('ALTER INDEX idx_6a41727a1eae1e45 RENAME TO idx_6a41727a_original');
        $this->addSql('ALTER TABLE companies DROP phone');
        $this->addSql('ALTER TABLE companies DROP whatsapp_connection_status');
        $this->addSql('ALTER TABLE companies DROP whatsapp_last_checked');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250828194255 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE professional_services ADD available_monday BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE professional_services ADD available_tuesday BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE professional_services ADD available_wednesday BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE professional_services ADD available_thursday BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE professional_services ADD available_friday BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE professional_services ADD available_saturday BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE professional_services ADD available_sunday BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE professional_services DROP available_monday');
        $this->addSql('ALTER TABLE professional_services DROP available_tuesday');
        $this->addSql('ALTER TABLE professional_services DROP available_wednesday');
        $this->addSql('ALTER TABLE professional_services DROP available_thursday');
        $this->addSql('ALTER TABLE professional_services DROP available_friday');
        $this->addSql('ALTER TABLE professional_services DROP available_saturday');
        $this->addSql('ALTER TABLE professional_services DROP available_sunday');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250919233720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add image URL fields to Company, Service and Professional entities';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE companies ADD logo_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE companies ADD cover_url VARCHAR(500) DEFAULT NULL');
        
        // Service images
        $this->addSql('ALTER TABLE services ADD image_url1 VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE services ADD image_url2 VARCHAR(500) DEFAULT NULL');
        
        // Professional image
        $this->addSql('ALTER TABLE professionals ADD profile_image_url VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE companies DROP logo_url');
        $this->addSql('ALTER TABLE companies DROP cover_url');
        $this->addSql('ALTER TABLE services DROP image_url1');
        $this->addSql('ALTER TABLE services DROP image_url2');
        $this->addSql('ALTER TABLE professionals DROP profile_image_url');
    }
}

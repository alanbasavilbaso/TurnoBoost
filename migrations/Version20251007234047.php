<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251007234047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE companies ADD email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE companies ADD receive_email_notifications BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs 
        $this->addSql('ALTER TABLE companies DROP email');
        $this->addSql('ALTER TABLE companies DROP receive_email_notifications');
    }
}

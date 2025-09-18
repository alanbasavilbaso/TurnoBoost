<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250917114701 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification settings fields to Company entity';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE companies ADD email_notifications_enabled BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE companies ADD whatsapp_notifications_enabled BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE companies ADD reminder_email_enabled BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE companies ADD reminder_whatsapp_enabled BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE companies ADD first_reminder_hours_before_appointment INT DEFAULT 24 NOT NULL');
        $this->addSql('ALTER TABLE companies ADD second_reminder_enabled BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE companies ADD second_reminder_hours_before_appointment INT DEFAULT 2 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE companies DROP email_notifications_enabled');
        $this->addSql('ALTER TABLE companies DROP whatsapp_notifications_enabled');
        $this->addSql('ALTER TABLE companies DROP reminder_email_enabled');
        $this->addSql('ALTER TABLE companies DROP reminder_whatsapp_enabled');
        $this->addSql('ALTER TABLE companies DROP first_reminder_hours_before_appointment');
        $this->addSql('ALTER TABLE companies DROP second_reminder_enabled');
        $this->addSql('ALTER TABLE companies DROP second_reminder_hours_before_appointment');
    }
}

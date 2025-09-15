<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250915200116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE companies ADD cancellable_bookings BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE companies ADD editable_bookings BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE companies ADD minimum_edit_time INT DEFAULT 120 NOT NULL');
        $this->addSql('ALTER TABLE companies ADD maximum_edits INT DEFAULT 3 NOT NULL');
        $this->addSql('ALTER TABLE companies ADD online_booking_enabled BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER INDEX idx_sss_special_schedule RENAME TO IDX_B3BEA1FD6F8E117D');
        $this->addSql('ALTER INDEX idx_sss_service RENAME TO IDX_B3BEA1FDED5CA9E6');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER INDEX idx_b3bea1fded5ca9e6 RENAME TO idx_sss_service');
        $this->addSql('ALTER INDEX idx_b3bea1fd6f8e117d RENAME TO idx_sss_special_schedule');
        $this->addSql('ALTER TABLE companies DROP cancellable_bookings');
        $this->addSql('ALTER TABLE companies DROP editable_bookings');
        $this->addSql('ALTER TABLE companies DROP minimum_edit_time');
        $this->addSql('ALTER TABLE companies DROP maximum_edits');
        $this->addSql('ALTER TABLE companies DROP online_booking_enabled');
    }
}

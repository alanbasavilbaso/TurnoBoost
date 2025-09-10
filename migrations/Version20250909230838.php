<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250909230838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE location_availability (id SERIAL NOT NULL, location_id INT NOT NULL, week_day INT NOT NULL, start_time TIME(0) WITHOUT TIME ZONE NOT NULL, end_time TIME(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4E10F2D164D218E ON location_availability (location_id)');
        $this->addSql('ALTER TABLE location_availability ADD CONSTRAINT FK_4E10F2D164D218E FOREIGN KEY (location_id) REFERENCES locations (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX idx_pb_active');
        $this->addSql('DROP INDEX idx_pb_block_type');
        $this->addSql('DROP INDEX idx_pb_date_range');
        $this->addSql('DROP INDEX idx_pb_company_professional');
        $this->addSql('ALTER TABLE professional_blocks ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE professional_blocks ALTER created_at SET NOT NULL');
        $this->addSql('ALTER TABLE professional_blocks ALTER updated_at DROP DEFAULT');
        $this->addSql('ALTER TABLE professional_blocks ALTER updated_at SET NOT NULL');
        $this->addSql('ALTER TABLE professional_blocks ALTER active DROP DEFAULT');
        $this->addSql('ALTER TABLE professional_blocks ALTER active SET NOT NULL');
        $this->addSql('ALTER TABLE special_schedules DROP CONSTRAINT fk_ss_professional');
        $this->addSql('ALTER TABLE special_schedules DROP CONSTRAINT fk_ss_user');
        $this->addSql('DROP INDEX idx_ss_date');
        $this->addSql('DROP INDEX idx_ss_professional_date');
        $this->addSql('ALTER TABLE special_schedules ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE special_schedules ALTER created_at SET NOT NULL');
        $this->addSql('ALTER TABLE special_schedules ALTER updated_at DROP DEFAULT');
        $this->addSql('ALTER TABLE special_schedules ALTER updated_at SET NOT NULL');
        $this->addSql('ALTER TABLE special_schedules ADD CONSTRAINT FK_5FD06702DB77003 FOREIGN KEY (professional_id) REFERENCES professionals (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE special_schedules ADD CONSTRAINT FK_5FD06702A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE location_availability DROP CONSTRAINT FK_4E10F2D164D218E');
        $this->addSql('DROP TABLE location_availability');
        $this->addSql('ALTER TABLE special_schedules DROP CONSTRAINT FK_5FD06702DB77003');
        $this->addSql('ALTER TABLE special_schedules DROP CONSTRAINT FK_5FD06702A76ED395');
        $this->addSql('ALTER TABLE special_schedules ALTER created_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE special_schedules ALTER created_at DROP NOT NULL');
        $this->addSql('ALTER TABLE special_schedules ALTER updated_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE special_schedules ALTER updated_at DROP NOT NULL');
        $this->addSql('ALTER TABLE special_schedules ADD CONSTRAINT fk_ss_professional FOREIGN KEY (professional_id) REFERENCES professionals (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE special_schedules ADD CONSTRAINT fk_ss_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_ss_date ON special_schedules (fecha)');
        $this->addSql('CREATE INDEX idx_ss_professional_date ON special_schedules (professional_id, fecha)');
        $this->addSql('ALTER TABLE professional_blocks ALTER created_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE professional_blocks ALTER created_at DROP NOT NULL');
        $this->addSql('ALTER TABLE professional_blocks ALTER updated_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE professional_blocks ALTER updated_at DROP NOT NULL');
        $this->addSql('ALTER TABLE professional_blocks ALTER active SET DEFAULT true');
        $this->addSql('ALTER TABLE professional_blocks ALTER active DROP NOT NULL');
        $this->addSql('CREATE INDEX idx_pb_active ON professional_blocks (active)');
        $this->addSql('CREATE INDEX idx_pb_block_type ON professional_blocks (block_type)');
        $this->addSql('CREATE INDEX idx_pb_date_range ON professional_blocks (start_date, end_date)');
        $this->addSql('CREATE INDEX idx_pb_company_professional ON professional_blocks (company_id, professional_id)');
    }
}

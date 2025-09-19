<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250918213759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add modification tracking fields to appointments table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appointments ADD original_appointment_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE appointments ADD modification_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE appointments ADD previous_appointment_id INT DEFAULT NULL');
        
        // Add foreign key constraints
        $this->addSql('ALTER TABLE appointments ADD CONSTRAINT FK_6A41727A_ORIGINAL FOREIGN KEY (original_appointment_id) REFERENCES appointments (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE appointments ADD CONSTRAINT FK_6A41727A_PREVIOUS FOREIGN KEY (previous_appointment_id) REFERENCES appointments (id) ON DELETE SET NULL');
        
        // Add indexes for better performance
        $this->addSql('CREATE INDEX IDX_6A41727A_ORIGINAL ON appointments (original_appointment_id)');
        $this->addSql('CREATE INDEX IDX_6A41727A_PREVIOUS ON appointments (previous_appointment_id)');
        $this->addSql('CREATE INDEX IDX_6A41727A_MODIFICATION_COUNT ON appointments (modification_count)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appointments DROP FOREIGN KEY FK_6A41727A_ORIGINAL');
        $this->addSql('ALTER TABLE appointments DROP FOREIGN KEY FK_6A41727A_PREVIOUS');
        $this->addSql('DROP INDEX IDX_6A41727A_ORIGINAL ON appointments');
        $this->addSql('DROP INDEX IDX_6A41727A_PREVIOUS ON appointments');
        $this->addSql('DROP INDEX IDX_6A41727A_MODIFICATION_COUNT ON appointments');
        $this->addSql('ALTER TABLE appointments DROP original_appointment_id');
        $this->addSql('ALTER TABLE appointments DROP modification_count');
        $this->addSql('ALTER TABLE appointments DROP previous_appointment_id');
    }
}

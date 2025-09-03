<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250902221958 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE appointments (id SERIAL NOT NULL, location_id INT NOT NULL, professional_id INT NOT NULL, patient_id INT DEFAULT NULL, service_id INT DEFAULT NULL, scheduled_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, duration_minutes INT NOT NULL, status VARCHAR(255) NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6A41727A64D218E ON appointments (location_id)');
        $this->addSql('CREATE INDEX IDX_6A41727ADB77003 ON appointments (professional_id)');
        $this->addSql('CREATE INDEX IDX_6A41727A6B899279 ON appointments (patient_id)');
        $this->addSql('CREATE INDEX IDX_6A41727AED5CA9E6 ON appointments (service_id)');
        $this->addSql('CREATE TABLE audit_log (id SERIAL NOT NULL, user_id INT DEFAULT NULL, entity_type VARCHAR(50) NOT NULL, entity_id INT NOT NULL, action VARCHAR(20) NOT NULL, old_values JSON DEFAULT NULL, new_values JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(500) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F6E1C0F5A76ED395 ON audit_log (user_id)');
        $this->addSql('COMMENT ON COLUMN audit_log.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE cron_job (id SERIAL NOT NULL, name VARCHAR(191) NOT NULL, command VARCHAR(1024) NOT NULL, schedule VARCHAR(191) NOT NULL, description VARCHAR(191) NOT NULL, enabled BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX un_name ON cron_job (name)');
        $this->addSql('CREATE TABLE cron_report (id SERIAL NOT NULL, job_id INT DEFAULT NULL, run_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, run_time DOUBLE PRECISION NOT NULL, exit_code INT NOT NULL, output TEXT NOT NULL, error TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B6C6A7F5BE04EA9 ON cron_report (job_id)');
        $this->addSql('CREATE TABLE feedback (id SERIAL NOT NULL, appointment_id INT NOT NULL, rating INT DEFAULT NULL, comment TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D2294458E5B533F9 ON feedback (appointment_id)');
        $this->addSql('CREATE TABLE locations (id SERIAL NOT NULL, created_by_id INT NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, domain VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_17E64ABAA7A91E0B ON locations (domain)');
        $this->addSql('CREATE INDEX IDX_17E64ABAB03A8386 ON locations (created_by_id)');
        $this->addSql('CREATE TABLE notifications (id SERIAL NOT NULL, appointment_id INT NOT NULL, type VARCHAR(255) NOT NULL, template_used VARCHAR(255) DEFAULT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6000B0D3E5B533F9 ON notifications (appointment_id)');
        $this->addSql('CREATE TABLE patients (id SERIAL NOT NULL, location_id INT NOT NULL, name VARCHAR(255) NOT NULL, phone VARCHAR(50) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2CCC2E2C64D218E ON patients (location_id)');
        $this->addSql('CREATE TABLE professional_availability (id SERIAL NOT NULL, professional_id INT NOT NULL, weekday INT NOT NULL, start_time TIME(0) WITHOUT TIME ZONE NOT NULL, end_time TIME(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_546E792DDB77003 ON professional_availability (professional_id)');
        $this->addSql('CREATE TABLE professional_services (id SERIAL NOT NULL, professional_id INT NOT NULL, service_id INT NOT NULL, custom_duration_minutes INT DEFAULT NULL, custom_price NUMERIC(10, 2) DEFAULT NULL, available_monday BOOLEAN DEFAULT true NOT NULL, available_tuesday BOOLEAN DEFAULT true NOT NULL, available_wednesday BOOLEAN DEFAULT true NOT NULL, available_thursday BOOLEAN DEFAULT true NOT NULL, available_friday BOOLEAN DEFAULT true NOT NULL, available_saturday BOOLEAN DEFAULT true NOT NULL, available_sunday BOOLEAN DEFAULT true NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E0B43BD4DB77003 ON professional_services (professional_id)');
        $this->addSql('CREATE INDEX IDX_E0B43BD4ED5CA9E6 ON professional_services (service_id)');
        $this->addSql('CREATE TABLE professionals (id SERIAL NOT NULL, location_id INT NOT NULL, name VARCHAR(255) NOT NULL, specialty VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, active BOOLEAN DEFAULT true NOT NULL, online_booking_enabled BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2DBE308E64D218E ON professionals (location_id)');
        $this->addSql('CREATE TABLE schedule_blocks (id SERIAL NOT NULL, professional_id INT NOT NULL, start_datetime TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_datetime TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, reason TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_ACCCD167DB77003 ON schedule_blocks (professional_id)');
        $this->addSql('CREATE TABLE services (id SERIAL NOT NULL, location_id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, default_duration_minutes INT NOT NULL, price NUMERIC(10, 2) DEFAULT NULL, active BOOLEAN DEFAULT true NOT NULL, online_booking_enabled BOOLEAN DEFAULT true NOT NULL, reminder_note TEXT DEFAULT NULL, delivery_type VARCHAR(255) DEFAULT \'in_person\' NOT NULL, service_type VARCHAR(255) DEFAULT \'regular\' NOT NULL, frequency_weeks INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7332E16964D218E ON services (location_id)');
        $this->addSql('CREATE TABLE users (id SERIAL NOT NULL, location_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE INDEX IDX_1483A5E964D218E ON users (location_id)');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify(\'messenger_messages\', NEW.queue_name::text);
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql;');
        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages;');
        $this->addSql('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages();');
        $this->addSql('ALTER TABLE appointments ADD CONSTRAINT FK_6A41727A64D218E FOREIGN KEY (location_id) REFERENCES locations (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE appointments ADD CONSTRAINT FK_6A41727ADB77003 FOREIGN KEY (professional_id) REFERENCES professionals (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE appointments ADD CONSTRAINT FK_6A41727A6B899279 FOREIGN KEY (patient_id) REFERENCES patients (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE appointments ADD CONSTRAINT FK_6A41727AED5CA9E6 FOREIGN KEY (service_id) REFERENCES services (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F5A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cron_report ADD CONSTRAINT FK_B6C6A7F5BE04EA9 FOREIGN KEY (job_id) REFERENCES cron_job (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D2294458E5B533F9 FOREIGN KEY (appointment_id) REFERENCES appointments (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE locations ADD CONSTRAINT FK_17E64ABAB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3E5B533F9 FOREIGN KEY (appointment_id) REFERENCES appointments (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE patients ADD CONSTRAINT FK_2CCC2E2C64D218E FOREIGN KEY (location_id) REFERENCES locations (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE professional_availability ADD CONSTRAINT FK_546E792DDB77003 FOREIGN KEY (professional_id) REFERENCES professionals (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE professional_services ADD CONSTRAINT FK_E0B43BD4DB77003 FOREIGN KEY (professional_id) REFERENCES professionals (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE professional_services ADD CONSTRAINT FK_E0B43BD4ED5CA9E6 FOREIGN KEY (service_id) REFERENCES services (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE professionals ADD CONSTRAINT FK_2DBE308E64D218E FOREIGN KEY (location_id) REFERENCES locations (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE schedule_blocks ADD CONSTRAINT FK_ACCCD167DB77003 FOREIGN KEY (professional_id) REFERENCES professionals (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE services ADD CONSTRAINT FK_7332E16964D218E FOREIGN KEY (location_id) REFERENCES locations (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E964D218E FOREIGN KEY (location_id) REFERENCES locations (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE appointments DROP CONSTRAINT FK_6A41727A64D218E');
        $this->addSql('ALTER TABLE appointments DROP CONSTRAINT FK_6A41727ADB77003');
        $this->addSql('ALTER TABLE appointments DROP CONSTRAINT FK_6A41727A6B899279');
        $this->addSql('ALTER TABLE appointments DROP CONSTRAINT FK_6A41727AED5CA9E6');
        $this->addSql('ALTER TABLE audit_log DROP CONSTRAINT FK_F6E1C0F5A76ED395');
        $this->addSql('ALTER TABLE cron_report DROP CONSTRAINT FK_B6C6A7F5BE04EA9');
        $this->addSql('ALTER TABLE feedback DROP CONSTRAINT FK_D2294458E5B533F9');
        $this->addSql('ALTER TABLE locations DROP CONSTRAINT FK_17E64ABAB03A8386');
        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT FK_6000B0D3E5B533F9');
        $this->addSql('ALTER TABLE patients DROP CONSTRAINT FK_2CCC2E2C64D218E');
        $this->addSql('ALTER TABLE professional_availability DROP CONSTRAINT FK_546E792DDB77003');
        $this->addSql('ALTER TABLE professional_services DROP CONSTRAINT FK_E0B43BD4DB77003');
        $this->addSql('ALTER TABLE professional_services DROP CONSTRAINT FK_E0B43BD4ED5CA9E6');
        $this->addSql('ALTER TABLE professionals DROP CONSTRAINT FK_2DBE308E64D218E');
        $this->addSql('ALTER TABLE schedule_blocks DROP CONSTRAINT FK_ACCCD167DB77003');
        $this->addSql('ALTER TABLE services DROP CONSTRAINT FK_7332E16964D218E');
        $this->addSql('ALTER TABLE users DROP CONSTRAINT FK_1483A5E964D218E');
        $this->addSql('DROP TABLE appointments');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE cron_job');
        $this->addSql('DROP TABLE cron_report');
        $this->addSql('DROP TABLE feedback');
        $this->addSql('DROP TABLE locations');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE patients');
        $this->addSql('DROP TABLE professional_availability');
        $this->addSql('DROP TABLE professional_services');
        $this->addSql('DROP TABLE professionals');
        $this->addSql('DROP TABLE schedule_blocks');
        $this->addSql('DROP TABLE services');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}

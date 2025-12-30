<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251229024646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE archive (
              id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
              user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
              job_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
              archived_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              is_deleted TINYINT(1) NOT NULL,
              INDEX IDX_D5FC5D9CA76ED395 (user_id),
              INDEX IDX_D5FC5D9CBE04EA9 (job_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE b_application_notes (
              id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
              user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
              application_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
              content LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              INDEX IDX_8C016DF9A76ED395 (user_id),
              INDEX IDX_8C016DF93E030ACD (application_id),
              UNIQUE INDEX uniq_user_application_note (user_id, application_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE b_job_notes (
              id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
              user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
              job_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
              content LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              INDEX IDX_CD6E4C82A76ED395 (user_id),
              INDEX IDX_CD6E4C82BE04EA9 (job_id),
              UNIQUE INDEX uniq_user_job_note (user_id, job_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE b_link_tracking_log (
              id INT AUTO_INCREMENT NOT NULL,
              credentialing_link_id INT NOT NULL,
              action VARCHAR(50) NOT NULL,
              ip_address VARCHAR(255) DEFAULT NULL,
              user_agent LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              INDEX IDX_546E37BB4B3F6B09 (credentialing_link_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE b_package (
              id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
              name VARCHAR(50) NOT NULL,
              type VARCHAR(20) NOT NULL,
              target VARCHAR(20) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              price NUMERIC(10, 2) NOT NULL,
              duration_days INT NOT NULL,
              max_job_posts INT DEFAULT NULL,
              max_applications INT DEFAULT NULL,
              is_active TINYINT(1) DEFAULT 1 NOT NULL,
              is_default TINYINT(1) DEFAULT 0 NOT NULL,
              features JSON DEFAULT NULL,
              stripe_price_id VARCHAR(255) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE b_payment (
              id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
              package_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
              user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
              for_user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
              amount NUMERIC(10, 2) NOT NULL,
              currency VARCHAR(3) NOT NULL,
              status VARCHAR(50) NOT NULL,
              stripe_session_id VARCHAR(255) DEFAULT NULL,
              stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              completed_at DATETIME DEFAULT NULL,
              INDEX IDX_E8DE0795F44CABFF (package_id),
              INDEX IDX_E8DE0795A76ED395 (user_id),
              INDEX IDX_E8DE07959B5BB4B8 (for_user_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE reset_password_request (
              id INT AUTO_INCREMENT NOT NULL,
              user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
              selector VARCHAR(20) NOT NULL,
              hashed_token VARCHAR(100) NOT NULL,
              requested_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              INDEX IDX_7CE748AA76ED395 (user_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              archive
            ADD
              CONSTRAINT FK_D5FC5D9CA76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              archive
            ADD
              CONSTRAINT FK_D5FC5D9CBE04EA9 FOREIGN KEY (job_id) REFERENCES b_job (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_application_notes
            ADD
              CONSTRAINT FK_8C016DF9A76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_application_notes
            ADD
              CONSTRAINT FK_8C016DF93E030ACD FOREIGN KEY (application_id) REFERENCES b_application (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_job_notes
            ADD
              CONSTRAINT FK_CD6E4C82A76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_job_notes
            ADD
              CONSTRAINT FK_CD6E4C82BE04EA9 FOREIGN KEY (job_id) REFERENCES b_job (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_link_tracking_log
            ADD
              CONSTRAINT FK_546E37BB4B3F6B09 FOREIGN KEY (credentialing_link_id) REFERENCES b_credentialing_links (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_payment
            ADD
              CONSTRAINT FK_E8DE0795F44CABFF FOREIGN KEY (package_id) REFERENCES b_package (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_payment
            ADD
              CONSTRAINT FK_E8DE0795A76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_payment
            ADD
              CONSTRAINT FK_E8DE07959B5BB4B8 FOREIGN KEY (for_user_id) REFERENCES b_user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              reset_password_request
            ADD
              CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id)
        SQL);
        $this->addSql('ALTER TABLE b_application DROP archived, ADD PRIMARY KEY (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_application
            ADD
              CONSTRAINT FK_E0E880C741CD9E7A FOREIGN KEY (employer_id) REFERENCES b_employer (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_application
            ADD
              CONSTRAINT FK_E0E880C7156BE243 FOREIGN KEY (recruiter_id) REFERENCES b_recruiter (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_application
            ADD
              CONSTRAINT FK_E0E880C7A53A8AA FOREIGN KEY (provider_id) REFERENCES b_provider (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_application
            ADD
              CONSTRAINT FK_E0E880C7BE04EA9 FOREIGN KEY (job_id) REFERENCES b_job (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_application
            ADD
              CONSTRAINT FK_E0E880C755D69D95 FOREIGN KEY (interview_id) REFERENCES b_interview (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_E0E880C741CD9E7A ON b_application (employer_id)');
        $this->addSql('CREATE INDEX IDX_E0E880C7156BE243 ON b_application (recruiter_id)');
        $this->addSql('CREATE INDEX IDX_E0E880C7A53A8AA ON b_application (provider_id)');
        $this->addSql('CREATE INDEX IDX_E0E880C7BE04EA9 ON b_application (job_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E0E880C755D69D95 ON b_application (interview_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_bookmark
            CHANGE
              `rank` `rank` DOUBLE PRECISION DEFAULT NULL,
            ADD
              PRIMARY KEY (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_bookmark
            ADD
              CONSTRAINT FK_24337FE8A76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_bookmark
            ADD
              CONSTRAINT FK_24337FE8BE04EA9 FOREIGN KEY (job_id) REFERENCES b_job (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_24337FE8A76ED395 ON b_bookmark (user_id)');
        $this->addSql('CREATE INDEX IDX_24337FE8BE04EA9 ON b_bookmark (job_id)');
        $this->addSql('ALTER TABLE b_cashback ADD PRIMARY KEY (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_cashback
            ADD
              CONSTRAINT FK_80A88F5D41CD9E7A FOREIGN KEY (employer_id) REFERENCES b_employer (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_cashback
            ADD
              CONSTRAINT FK_80A88F5DA53A8AA FOREIGN KEY (provider_id) REFERENCES b_provider (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_cashback
            ADD
              CONSTRAINT FK_80A88F5D2989F1FD FOREIGN KEY (invoice_id) REFERENCES b_invoice (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_cashback
            ADD
              CONSTRAINT FK_80A88F5DBE04EA9 FOREIGN KEY (job_id) REFERENCES b_job (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_80A88F5D41CD9E7A ON b_cashback (employer_id)');
        $this->addSql('CREATE INDEX IDX_80A88F5DA53A8AA ON b_cashback (provider_id)');
        $this->addSql('CREATE INDEX IDX_80A88F5D2989F1FD ON b_cashback (invoice_id)');
        $this->addSql('CREATE INDEX IDX_80A88F5DBE04EA9 ON b_cashback (job_id)');
        $this->addSql('ALTER TABLE b_config ADD PRIMARY KEY (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_47396F2E95D1CAA6 ON b_config (config_key)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_credentialing_links
            CHANGE
              submitted_at submitted_at DATETIME DEFAULT NULL,
            CHANGE
              last_opened_at last_opened_at DATETIME DEFAULT NULL,
            CHANGE
              open_count open_count INT NOT NULL,
            CHANGE
              completed_at completed_at DATETIME DEFAULT NULL,
            CHANGE
              provider_response provider_response LONGTEXT DEFAULT NULL,
            CHANGE
              is_active is_active TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_credentialing_links
            ADD
              CONSTRAINT FK_33159967A53A8AA FOREIGN KEY (provider_id) REFERENCES b_provider (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_credentialing_links
            ADD
              CONSTRAINT FK_33159967BE04EA9 FOREIGN KEY (job_id) REFERENCES b_job (id)
        SQL);
        $this->addSql('ALTER TABLE b_credentialing_links RENAME INDEX idx_provider_id TO IDX_33159967A53A8AA');
        $this->addSql('ALTER TABLE b_credentialing_links RENAME INDEX idx_job_id TO IDX_33159967BE04EA9');
        $this->addSql('ALTER TABLE b_document CHANGE name name VARCHAR(255) DEFAULT NULL, ADD PRIMARY KEY (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_document
            ADD
              CONSTRAINT FK_26386783A76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_document
            ADD
              CONSTRAINT FK_263867833E030ACD FOREIGN KEY (application_id) REFERENCES b_application (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_document
            ADD
              CONSTRAINT FK_26386783A53A8AA FOREIGN KEY (provider_id) REFERENCES b_user (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_26386783A76ED395 ON b_document (user_id)');
        $this->addSql('ALTER TABLE b_document RENAME INDEX idx_1520df103e030acd TO IDX_263867833E030ACD');
        $this->addSql('ALTER TABLE b_document RENAME INDEX idx_6cd4c1fa6dcfd9e TO IDX_26386783A53A8AA');
        $this->addSql('ALTER TABLE b_document_request ADD PRIMARY KEY (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_document_request
            ADD
              CONSTRAINT FK_7AD2C1D4A53A8AA FOREIGN KEY (provider_id) REFERENCES b_provider (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_document_request
            ADD
              CONSTRAINT FK_7AD2C1D44DA1E751 FOREIGN KEY (requested_by_id) REFERENCES b_recruiter (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_document_request
            ADD
              CONSTRAINT FK_7AD2C1D43E030ACD FOREIGN KEY (application_id) REFERENCES b_application (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_document_request
            ADD
              CONSTRAINT FK_7AD2C1D4C33F7837 FOREIGN KEY (document_id) REFERENCES b_document (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_7AD2C1D4A53A8AA ON b_document_request (provider_id)');
        $this->addSql('CREATE INDEX IDX_7AD2C1D44DA1E751 ON b_document_request (requested_by_id)');
        $this->addSql('CREATE INDEX IDX_7AD2C1D43E030ACD ON b_document_request (application_id)');
        $this->addSql('CREATE INDEX IDX_7AD2C1D4C33F7837 ON b_document_request (document_id)');
        $this->addSql('ALTER TABLE b_employer ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE b_interview ADD PRIMARY KEY (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_interview
            ADD
              CONSTRAINT FK_2346B4A3E030ACD FOREIGN KEY (application_id) REFERENCES b_application (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_2346B4A3E030ACD ON b_interview (application_id)');
        $this->addSql('ALTER TABLE b_invoice ADD PRIMARY KEY (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_invoice
            ADD
              CONSTRAINT FK_159394DC41CD9E7A FOREIGN KEY (employer_id) REFERENCES b_employer (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_invoice
            ADD
              CONSTRAINT FK_159394DC156BE243 FOREIGN KEY (recruiter_id) REFERENCES b_recruiter (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_invoice
            ADD
              CONSTRAINT FK_159394DCA53A8AA FOREIGN KEY (provider_id) REFERENCES b_provider (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_invoice
            ADD
              CONSTRAINT FK_159394DCBE04EA9 FOREIGN KEY (job_id) REFERENCES b_job (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_159394DC41CD9E7A ON b_invoice (employer_id)');
        $this->addSql('CREATE INDEX IDX_159394DC156BE243 ON b_invoice (recruiter_id)');
        $this->addSql('CREATE INDEX IDX_159394DCA53A8AA ON b_invoice (provider_id)');
        $this->addSql('CREATE INDEX IDX_159394DCBE04EA9 ON b_invoice (job_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_job
            DROP
              archived,
            DROP
              ank,
            CHANGE
              annual_salary annual_salary INT DEFAULT NULL,
            CHANGE
              monthly_salary monthly_salary INT DEFAULT NULL,
            ADD
              PRIMARY KEY (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_job
            ADD
              CONSTRAINT FK_B227F52EA76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_job
            ADD
              CONSTRAINT FK_B227F52E41CD9E7A FOREIGN KEY (employer_id) REFERENCES b_employer (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_job
            ADD
              CONSTRAINT FK_B227F52EFDEF8996 FOREIGN KEY (profession_id) REFERENCES b_profession (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_job
            ADD
              CONSTRAINT FK_B227F52E3B5A08D7 FOREIGN KEY (speciality_id) REFERENCES b_speciality (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_B227F52EA76ED395 ON b_job (user_id)');
        $this->addSql('CREATE INDEX IDX_B227F52E41CD9E7A ON b_job (employer_id)');
        $this->addSql('CREATE INDEX IDX_B227F52EFDEF8996 ON b_job (profession_id)');
        $this->addSql('CREATE INDEX IDX_B227F52E3B5A08D7 ON b_job (speciality_id)');
        $this->addSql('ALTER TABLE b_job_recruiter DROP FOREIGN KEY FK_JR_RECRUITER');
        $this->addSql('ALTER TABLE b_job_recruiter CHANGE status status VARCHAR(50) DEFAULT \'Assigned\' NOT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_job_recruiter
            ADD
              CONSTRAINT FK_5AB612B5BE04EA9 FOREIGN KEY (job_id) REFERENCES b_job (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_job_recruiter
            ADD
              CONSTRAINT FK_5AB612B5156BE243 FOREIGN KEY (recruiter_id) REFERENCES b_recruiter (id)
        SQL);
        $this->addSql('ALTER TABLE b_job_recruiter RENAME INDEX idx_job_id TO IDX_5AB612B5BE04EA9');
        $this->addSql('ALTER TABLE b_job_recruiter RENAME INDEX idx_recruiter_id TO IDX_5AB612B5156BE243');
        $this->addSql('ALTER TABLE b_job_report ADD PRIMARY KEY (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_job_report
            ADD
              CONSTRAINT FK_235B2F69A76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_job_report
            ADD
              CONSTRAINT FK_235B2F69BE04EA9 FOREIGN KEY (job_id) REFERENCES b_job (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_235B2F69A76ED395 ON b_job_report (user_id)');
        $this->addSql('CREATE INDEX IDX_235B2F69BE04EA9 ON b_job_report (job_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_message
            ADD
              recruiter_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            DROP
              type,
            CHANGE
              receiver_id receiver_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            CHANGE
              deleted deleted TINYINT(1) DEFAULT 0 NOT NULL,
            CHANGE
              job_uuid job_uuid BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            ADD
              PRIMARY KEY (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_message
            ADD
              CONSTRAINT FK_334BB3E7727ACA70 FOREIGN KEY (parent_id) REFERENCES b_message (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_message
            ADD
              CONSTRAINT FK_334BB3E7F624B39D FOREIGN KEY (sender_id) REFERENCES b_user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_message
            ADD
              CONSTRAINT FK_334BB3E7CD53EDB6 FOREIGN KEY (receiver_id) REFERENCES b_user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_message
            ADD
              CONSTRAINT FK_334BB3E741CD9E7A FOREIGN KEY (employer_id) REFERENCES b_employer (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_message
            ADD
              CONSTRAINT FK_334BB3E7156BE243 FOREIGN KEY (recruiter_id) REFERENCES b_recruiter (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_message
            ADD
              CONSTRAINT FK_334BB3E756B326E7 FOREIGN KEY (job_uuid) REFERENCES b_job (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_message
            ADD
              CONSTRAINT FK_334BB3E73E030ACD FOREIGN KEY (application_id) REFERENCES b_application (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_334BB3E7727ACA70 ON b_message (parent_id)');
        $this->addSql('CREATE INDEX IDX_334BB3E7F624B39D ON b_message (sender_id)');
        $this->addSql('CREATE INDEX IDX_334BB3E7CD53EDB6 ON b_message (receiver_id)');
        $this->addSql('CREATE INDEX IDX_334BB3E741CD9E7A ON b_message (employer_id)');
        $this->addSql('CREATE INDEX IDX_334BB3E7156BE243 ON b_message (recruiter_id)');
        $this->addSql('ALTER TABLE b_message RENAME INDEX idx_b_message_job_uuid TO IDX_334BB3E756B326E7');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_notification
            CHANGE
              extra_data extra_data JSON DEFAULT NULL,
            ADD
              PRIMARY KEY (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_notification
            ADD
              CONSTRAINT FK_567360A2A76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_567360A2A76ED395 ON b_notification (user_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_package_subscription
            CHANGE
              id id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
            CHANGE
              user_id user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
            CHANGE
              package_id package_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
            CHANGE
              status status VARCHAR(20) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_package_subscription
            ADD
              CONSTRAINT FK_3FB7F97EA76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_package_subscription
            ADD
              CONSTRAINT FK_3FB7F97EF44CABFF FOREIGN KEY (package_id) REFERENCES b_package (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_package_subscription RENAME INDEX idx_package_subscription_user_id TO IDX_3FB7F97EA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_package_subscription RENAME INDEX idx_package_subscription_package_id TO IDX_3FB7F97EF44CABFF
        SQL);
        $this->addSql('ALTER TABLE b_profession ADD PRIMARY KEY (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DE378055E237E06 ON b_profession (name)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_provider
            CHANGE
              desired_states desired_states JSON DEFAULT NULL,
            CHANGE
              cashback cashback JSON DEFAULT NULL,
            CHANGE
              notification_preferences notification_preferences JSON DEFAULT NULL,
            ADD
              PRIMARY KEY (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_provider
            ADD
              CONSTRAINT FK_6C959E69A76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_provider
            ADD
              CONSTRAINT FK_6C959E69FDEF8996 FOREIGN KEY (profession_id) REFERENCES b_profession (id)
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6C959E69A76ED395 ON b_provider (user_id)');
        $this->addSql('CREATE INDEX IDX_6C959E69FDEF8996 ON b_provider (profession_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              provider_speciality
            CHANGE
              provider_id provider_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
            CHANGE
              speciality_id speciality_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              provider_speciality
            ADD
              CONSTRAINT FK_D661EC36A53A8AA FOREIGN KEY (provider_id) REFERENCES b_provider (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              provider_speciality
            ADD
              CONSTRAINT FK_D661EC363B5A08D7 FOREIGN KEY (speciality_id) REFERENCES b_speciality (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              provider_speciality RENAME INDEX idx_provider_speciality_provider TO IDX_D661EC36A53A8AA
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              provider_speciality RENAME INDEX idx_provider_speciality_speciality TO IDX_D661EC363B5A08D7
        SQL);
        $this->addSql('ALTER TABLE b_provider_education ADD PRIMARY KEY (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_provider_education
            ADD
              CONSTRAINT FK_9245D5DDA76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_9245D5DDA76ED395 ON b_provider_education (user_id)');
        $this->addSql('ALTER TABLE b_provider_experience ADD PRIMARY KEY (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_provider_experience
            ADD
              CONSTRAINT FK_95669319A76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_95669319A76ED395 ON b_provider_experience (user_id)');
        $this->addSql('ALTER TABLE b_provider_insurance ADD PRIMARY KEY (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_provider_insurance
            ADD
              CONSTRAINT FK_2D412443A76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_2D412443A76ED395 ON b_provider_insurance (user_id)');
        $this->addSql('ALTER TABLE b_provider_license ADD PRIMARY KEY (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_provider_license
            ADD
              CONSTRAINT FK_421ABE29A76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_421ABE29A76ED395 ON b_provider_license (user_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_recruiter
            DROP
              INDEX idx_user_id,
            ADD
              UNIQUE INDEX UNIQ_13AF64A6A76ED395 (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_recruiter
            CHANGE
              speciality speciality VARCHAR(255) DEFAULT NULL,
            CHANGE
              membership_level membership_level VARCHAR(50) DEFAULT 'Silver' NOT NULL,
            CHANGE
              rating rating NUMERIC(3, 2) DEFAULT '0' NOT NULL,
            CHANGE
              is_verified is_verified TINYINT(1) DEFAULT 0 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_recruiter
            ADD
              CONSTRAINT FK_13AF64A6A76ED395 FOREIGN KEY (user_id) REFERENCES b_user (id)
        SQL);
        $this->addSql('ALTER TABLE b_review ADD PRIMARY KEY (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_review
            ADD
              CONSTRAINT FK_EAF0C194A53A8AA FOREIGN KEY (provider_id) REFERENCES b_provider (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_review
            ADD
              CONSTRAINT FK_EAF0C19441CD9E7A FOREIGN KEY (employer_id) REFERENCES b_employer (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_review
            ADD
              CONSTRAINT FK_EAF0C1943E030ACD FOREIGN KEY (application_id) REFERENCES b_application (id) ON DELETE CASCADE
        SQL);
        $this->addSql('CREATE INDEX IDX_EAF0C194A53A8AA ON b_review (provider_id)');
        $this->addSql('CREATE INDEX IDX_EAF0C19441CD9E7A ON b_review (employer_id)');
        $this->addSql('CREATE INDEX IDX_EAF0C1943E030ACD ON b_review (application_id)');
        $this->addSql('ALTER TABLE b_speciality ADD PRIMARY KEY (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_speciality
            ADD
              CONSTRAINT FK_44A7D5E2FDEF8996 FOREIGN KEY (profession_id) REFERENCES b_profession (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_44A7D5E2FDEF8996 ON b_speciality (profession_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_user
            DROP
              profile_picture,
            DROP
              provider_id,
            CHANGE
              roles roles JSON NOT NULL,
            CHANGE
              oauth_data oauth_data JSON DEFAULT NULL,
            ADD
              PRIMARY KEY (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_user
            ADD
              CONSTRAINT FK_E26A5EBD41CD9E7A FOREIGN KEY (employer_id) REFERENCES b_employer (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_user
            ADD
              CONSTRAINT FK_E26A5EBD156BE243 FOREIGN KEY (recruiter_id) REFERENCES b_recruiter (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_E26A5EBD41CD9E7A ON b_user (employer_id)');
        $this->addSql('CREATE INDEX IDX_E26A5EBD156BE243 ON b_user (recruiter_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON b_user (email)');
        $this->addSql('ALTER TABLE b_workflow_log ADD PRIMARY KEY (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_workflow_log
            ADD
              CONSTRAINT FK_26543080FC2869F0 FOREIGN KEY (transitioned_by_id) REFERENCES b_user (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_26543080FC2869F0 ON b_workflow_log (transitioned_by_id)');
        $this->addSql('DROP INDEX IDX_to_do_is_completed ON to_do');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              to_do
            ADD
              recruiter_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            CHANGE
              id id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
            CHANGE
              provider_id provider_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
            CHANGE
              employer_id employer_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            CHANGE
              description description LONGTEXT DEFAULT NULL,
            CHANGE
              document_request_id document_request_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            CHANGE
              is_completed is_completed TINYINT(1) NOT NULL,
            CHANGE
              bookmark_id bookmark_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            CHANGE
              job_id job_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              to_do
            ADD
              CONSTRAINT FK_1249EDA0A53A8AA FOREIGN KEY (provider_id) REFERENCES b_provider (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              to_do
            ADD
              CONSTRAINT FK_1249EDA041CD9E7A FOREIGN KEY (employer_id) REFERENCES b_employer (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              to_do
            ADD
              CONSTRAINT FK_1249EDA0156BE243 FOREIGN KEY (recruiter_id) REFERENCES b_recruiter (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              to_do
            ADD
              CONSTRAINT FK_1249EDA092741D25 FOREIGN KEY (bookmark_id) REFERENCES b_bookmark (id)
        SQL);
        $this->addSql('ALTER TABLE to_do ADD CONSTRAINT FK_1249EDA0BE04EA9 FOREIGN KEY (job_id) REFERENCES b_job (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              to_do
            ADD
              CONSTRAINT FK_1249EDA0E3BD13F3 FOREIGN KEY (document_request_id) REFERENCES b_document_request (id)
        SQL);
        $this->addSql('CREATE INDEX IDX_1249EDA0156BE243 ON to_do (recruiter_id)');
        $this->addSql('ALTER TABLE to_do RENAME INDEX idx_to_do_provider_id TO IDX_1249EDA0A53A8AA');
        $this->addSql('ALTER TABLE to_do RENAME INDEX idx_to_do_employer_id TO IDX_1249EDA041CD9E7A');
        $this->addSql('ALTER TABLE to_do RENAME INDEX idx_to_do_bookmark_id TO IDX_1249EDA092741D25');
        $this->addSql('ALTER TABLE to_do RENAME INDEX idx_to_do_job_id TO IDX_1249EDA0BE04EA9');
        $this->addSql('ALTER TABLE to_do RENAME INDEX idx_to_do_document_request_id TO IDX_1249EDA0E3BD13F3');
        $this->addSql('DROP INDEX IDX_MESSENGER_QUEUE ON messenger_messages');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              messenger_messages
            DROP
              queue,
            CHANGE
              queue_name queue_name VARCHAR(190) NOT NULL,
            CHANGE
              created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            CHANGE
              available_at available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            CHANGE
              delivered_at delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('ALTER TABLE messenger_messages RENAME INDEX idx_messenger_available_at TO IDX_75EA56E0E3BD61CE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE b_package_subscription DROP FOREIGN KEY FK_3FB7F97EF44CABFF');
        $this->addSql('ALTER TABLE archive DROP FOREIGN KEY FK_D5FC5D9CA76ED395');
        $this->addSql('ALTER TABLE archive DROP FOREIGN KEY FK_D5FC5D9CBE04EA9');
        $this->addSql('ALTER TABLE b_application_notes DROP FOREIGN KEY FK_8C016DF9A76ED395');
        $this->addSql('ALTER TABLE b_application_notes DROP FOREIGN KEY FK_8C016DF93E030ACD');
        $this->addSql('ALTER TABLE b_job_notes DROP FOREIGN KEY FK_CD6E4C82A76ED395');
        $this->addSql('ALTER TABLE b_job_notes DROP FOREIGN KEY FK_CD6E4C82BE04EA9');
        $this->addSql('ALTER TABLE b_link_tracking_log DROP FOREIGN KEY FK_546E37BB4B3F6B09');
        $this->addSql('ALTER TABLE b_payment DROP FOREIGN KEY FK_E8DE0795F44CABFF');
        $this->addSql('ALTER TABLE b_payment DROP FOREIGN KEY FK_E8DE0795A76ED395');
        $this->addSql('ALTER TABLE b_payment DROP FOREIGN KEY FK_E8DE07959B5BB4B8');
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('DROP TABLE archive');
        $this->addSql('DROP TABLE b_application_notes');
        $this->addSql('DROP TABLE b_job_notes');
        $this->addSql('DROP TABLE b_link_tracking_log');
        $this->addSql('DROP TABLE b_package');
        $this->addSql('DROP TABLE b_payment');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('ALTER TABLE b_application DROP FOREIGN KEY FK_E0E880C741CD9E7A');
        $this->addSql('ALTER TABLE b_application DROP FOREIGN KEY FK_E0E880C7156BE243');
        $this->addSql('ALTER TABLE b_application DROP FOREIGN KEY FK_E0E880C7A53A8AA');
        $this->addSql('ALTER TABLE b_application DROP FOREIGN KEY FK_E0E880C7BE04EA9');
        $this->addSql('ALTER TABLE b_application DROP FOREIGN KEY FK_E0E880C755D69D95');
        $this->addSql('DROP INDEX IDX_E0E880C741CD9E7A ON b_application');
        $this->addSql('DROP INDEX IDX_E0E880C7156BE243 ON b_application');
        $this->addSql('DROP INDEX IDX_E0E880C7A53A8AA ON b_application');
        $this->addSql('DROP INDEX IDX_E0E880C7BE04EA9 ON b_application');
        $this->addSql('DROP INDEX UNIQ_E0E880C755D69D95 ON b_application');
        $this->addSql('DROP INDEX `primary` ON b_application');
        $this->addSql('ALTER TABLE b_application ADD archived TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE b_bookmark DROP FOREIGN KEY FK_24337FE8A76ED395');
        $this->addSql('ALTER TABLE b_bookmark DROP FOREIGN KEY FK_24337FE8BE04EA9');
        $this->addSql('DROP INDEX IDX_24337FE8A76ED395 ON b_bookmark');
        $this->addSql('DROP INDEX IDX_24337FE8BE04EA9 ON b_bookmark');
        $this->addSql('DROP INDEX `primary` ON b_bookmark');
        $this->addSql('ALTER TABLE b_bookmark CHANGE `rank` `rank` NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE b_cashback DROP FOREIGN KEY FK_80A88F5D41CD9E7A');
        $this->addSql('ALTER TABLE b_cashback DROP FOREIGN KEY FK_80A88F5DA53A8AA');
        $this->addSql('ALTER TABLE b_cashback DROP FOREIGN KEY FK_80A88F5D2989F1FD');
        $this->addSql('ALTER TABLE b_cashback DROP FOREIGN KEY FK_80A88F5DBE04EA9');
        $this->addSql('DROP INDEX IDX_80A88F5D41CD9E7A ON b_cashback');
        $this->addSql('DROP INDEX IDX_80A88F5DA53A8AA ON b_cashback');
        $this->addSql('DROP INDEX IDX_80A88F5D2989F1FD ON b_cashback');
        $this->addSql('DROP INDEX IDX_80A88F5DBE04EA9 ON b_cashback');
        $this->addSql('DROP INDEX `primary` ON b_cashback');
        $this->addSql('DROP INDEX UNIQ_47396F2E95D1CAA6 ON b_config');
        $this->addSql('DROP INDEX `primary` ON b_config');
        $this->addSql('ALTER TABLE b_credentialing_links DROP FOREIGN KEY FK_33159967A53A8AA');
        $this->addSql('ALTER TABLE b_credentialing_links DROP FOREIGN KEY FK_33159967BE04EA9');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_credentialing_links
            CHANGE
              is_active is_active TINYINT(1) DEFAULT 1 NOT NULL,
            CHANGE
              submitted_at submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            CHANGE
              last_opened_at last_opened_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            CHANGE
              open_count open_count INT DEFAULT 0,
            CHANGE
              completed_at completed_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            CHANGE
              provider_response provider_response TEXT DEFAULT NULL
        SQL);
        $this->addSql('ALTER TABLE b_credentialing_links RENAME INDEX idx_33159967a53a8aa TO IDX_provider_id');
        $this->addSql('ALTER TABLE b_credentialing_links RENAME INDEX idx_33159967be04ea9 TO IDX_job_id');
        $this->addSql('ALTER TABLE b_document DROP FOREIGN KEY FK_26386783A76ED395');
        $this->addSql('ALTER TABLE b_document DROP FOREIGN KEY FK_263867833E030ACD');
        $this->addSql('ALTER TABLE b_document DROP FOREIGN KEY FK_26386783A53A8AA');
        $this->addSql('DROP INDEX IDX_26386783A76ED395 ON b_document');
        $this->addSql('DROP INDEX `primary` ON b_document');
        $this->addSql('ALTER TABLE b_document CHANGE name name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE b_document RENAME INDEX idx_263867833e030acd TO IDX_1520DF103E030ACD');
        $this->addSql('ALTER TABLE b_document RENAME INDEX idx_26386783a53a8aa TO IDX_6CD4C1FA6DCFD9E');
        $this->addSql('ALTER TABLE b_document_request DROP FOREIGN KEY FK_7AD2C1D4A53A8AA');
        $this->addSql('ALTER TABLE b_document_request DROP FOREIGN KEY FK_7AD2C1D44DA1E751');
        $this->addSql('ALTER TABLE b_document_request DROP FOREIGN KEY FK_7AD2C1D43E030ACD');
        $this->addSql('ALTER TABLE b_document_request DROP FOREIGN KEY FK_7AD2C1D4C33F7837');
        $this->addSql('DROP INDEX IDX_7AD2C1D4A53A8AA ON b_document_request');
        $this->addSql('DROP INDEX IDX_7AD2C1D44DA1E751 ON b_document_request');
        $this->addSql('DROP INDEX IDX_7AD2C1D43E030ACD ON b_document_request');
        $this->addSql('DROP INDEX IDX_7AD2C1D4C33F7837 ON b_document_request');
        $this->addSql('DROP INDEX `primary` ON b_document_request');
        $this->addSql('DROP INDEX `primary` ON b_employer');
        $this->addSql('ALTER TABLE b_interview DROP FOREIGN KEY FK_2346B4A3E030ACD');
        $this->addSql('DROP INDEX IDX_2346B4A3E030ACD ON b_interview');
        $this->addSql('DROP INDEX `primary` ON b_interview');
        $this->addSql('ALTER TABLE b_invoice DROP FOREIGN KEY FK_159394DC41CD9E7A');
        $this->addSql('ALTER TABLE b_invoice DROP FOREIGN KEY FK_159394DC156BE243');
        $this->addSql('ALTER TABLE b_invoice DROP FOREIGN KEY FK_159394DCA53A8AA');
        $this->addSql('ALTER TABLE b_invoice DROP FOREIGN KEY FK_159394DCBE04EA9');
        $this->addSql('DROP INDEX IDX_159394DC41CD9E7A ON b_invoice');
        $this->addSql('DROP INDEX IDX_159394DC156BE243 ON b_invoice');
        $this->addSql('DROP INDEX IDX_159394DCA53A8AA ON b_invoice');
        $this->addSql('DROP INDEX IDX_159394DCBE04EA9 ON b_invoice');
        $this->addSql('DROP INDEX `primary` ON b_invoice');
        $this->addSql('ALTER TABLE b_job DROP FOREIGN KEY FK_B227F52EA76ED395');
        $this->addSql('ALTER TABLE b_job DROP FOREIGN KEY FK_B227F52E41CD9E7A');
        $this->addSql('ALTER TABLE b_job DROP FOREIGN KEY FK_B227F52EFDEF8996');
        $this->addSql('ALTER TABLE b_job DROP FOREIGN KEY FK_B227F52E3B5A08D7');
        $this->addSql('DROP INDEX IDX_B227F52EA76ED395 ON b_job');
        $this->addSql('DROP INDEX IDX_B227F52E41CD9E7A ON b_job');
        $this->addSql('DROP INDEX IDX_B227F52EFDEF8996 ON b_job');
        $this->addSql('DROP INDEX IDX_B227F52E3B5A08D7 ON b_job');
        $this->addSql('DROP INDEX `primary` ON b_job');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_job
            ADD
              archived TINYINT(1) DEFAULT 0 NOT NULL,
            ADD
              ank INT DEFAULT NULL,
            CHANGE
              annual_salary annual_salary NUMERIC(10, 2) DEFAULT NULL,
            CHANGE
              monthly_salary monthly_salary NUMERIC(10, 2) DEFAULT NULL
        SQL);
        $this->addSql('ALTER TABLE b_job_recruiter DROP FOREIGN KEY FK_5AB612B5BE04EA9');
        $this->addSql('ALTER TABLE b_job_recruiter DROP FOREIGN KEY FK_5AB612B5156BE243');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_job_recruiter
            CHANGE
              status status VARCHAR(50) DEFAULT 'Assigned' COMMENT 'Assigned, Accepted, Rejected, Closed'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_job_recruiter
            ADD
              CONSTRAINT FK_JR_RECRUITER FOREIGN KEY (recruiter_id) REFERENCES b_recruiter (id) ON
            UPDATE
              NO ACTION ON DELETE CASCADE
        SQL);
        $this->addSql('ALTER TABLE b_job_recruiter RENAME INDEX idx_5ab612b5156be243 TO idx_recruiter_id');
        $this->addSql('ALTER TABLE b_job_recruiter RENAME INDEX idx_5ab612b5be04ea9 TO idx_job_id');
        $this->addSql('ALTER TABLE b_job_report DROP FOREIGN KEY FK_235B2F69A76ED395');
        $this->addSql('ALTER TABLE b_job_report DROP FOREIGN KEY FK_235B2F69BE04EA9');
        $this->addSql('DROP INDEX IDX_235B2F69A76ED395 ON b_job_report');
        $this->addSql('DROP INDEX IDX_235B2F69BE04EA9 ON b_job_report');
        $this->addSql('DROP INDEX `primary` ON b_job_report');
        $this->addSql('ALTER TABLE b_message DROP FOREIGN KEY FK_334BB3E7727ACA70');
        $this->addSql('ALTER TABLE b_message DROP FOREIGN KEY FK_334BB3E7F624B39D');
        $this->addSql('ALTER TABLE b_message DROP FOREIGN KEY FK_334BB3E7CD53EDB6');
        $this->addSql('ALTER TABLE b_message DROP FOREIGN KEY FK_334BB3E741CD9E7A');
        $this->addSql('ALTER TABLE b_message DROP FOREIGN KEY FK_334BB3E7156BE243');
        $this->addSql('ALTER TABLE b_message DROP FOREIGN KEY FK_334BB3E756B326E7');
        $this->addSql('ALTER TABLE b_message DROP FOREIGN KEY FK_334BB3E73E030ACD');
        $this->addSql('DROP INDEX IDX_334BB3E7727ACA70 ON b_message');
        $this->addSql('DROP INDEX IDX_334BB3E7F624B39D ON b_message');
        $this->addSql('DROP INDEX IDX_334BB3E7CD53EDB6 ON b_message');
        $this->addSql('DROP INDEX IDX_334BB3E741CD9E7A ON b_message');
        $this->addSql('DROP INDEX IDX_334BB3E7156BE243 ON b_message');
        $this->addSql('DROP INDEX `primary` ON b_message');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_message
            ADD
              type VARCHAR(20) DEFAULT 'inbox' NOT NULL,
            DROP
              recruiter_id,
            CHANGE
              receiver_id receiver_id BINARY(16) DEFAULT NULL,
            CHANGE
              job_uuid job_uuid BINARY(16) DEFAULT NULL,
            CHANGE
              deleted deleted TINYINT(1) DEFAULT 0
        SQL);
        $this->addSql('ALTER TABLE b_message RENAME INDEX idx_334bb3e756b326e7 TO IDX_b_message_job_uuid');
        $this->addSql('ALTER TABLE b_notification DROP FOREIGN KEY FK_567360A2A76ED395');
        $this->addSql('DROP INDEX IDX_567360A2A76ED395 ON b_notification');
        $this->addSql('DROP INDEX `primary` ON b_notification');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_notification
            CHANGE
              extra_data extra_data LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`
        SQL);
        $this->addSql('ALTER TABLE b_package_subscription DROP FOREIGN KEY FK_3FB7F97EA76ED395');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_package_subscription
            CHANGE
              id id BINARY(16) NOT NULL,
            CHANGE
              user_id user_id BINARY(16) NOT NULL,
            CHANGE
              package_id package_id BINARY(16) NOT NULL,
            CHANGE
              status status VARCHAR(20) DEFAULT 'pending' NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_package_subscription RENAME INDEX idx_3fb7f97ea76ed395 TO IDX_package_subscription_user_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_package_subscription RENAME INDEX idx_3fb7f97ef44cabff TO IDX_package_subscription_package_id
        SQL);
        $this->addSql('DROP INDEX UNIQ_DE378055E237E06 ON b_profession');
        $this->addSql('DROP INDEX `primary` ON b_profession');
        $this->addSql('ALTER TABLE b_provider DROP FOREIGN KEY FK_6C959E69A76ED395');
        $this->addSql('ALTER TABLE b_provider DROP FOREIGN KEY FK_6C959E69FDEF8996');
        $this->addSql('DROP INDEX UNIQ_6C959E69A76ED395 ON b_provider');
        $this->addSql('DROP INDEX IDX_6C959E69FDEF8996 ON b_provider');
        $this->addSql('DROP INDEX `primary` ON b_provider');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_provider
            CHANGE
              desired_states desired_states LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`,
            CHANGE
              cashback cashback LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`,
            CHANGE
              notification_preferences notification_preferences LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`
        SQL);
        $this->addSql('ALTER TABLE b_provider_education DROP FOREIGN KEY FK_9245D5DDA76ED395');
        $this->addSql('DROP INDEX IDX_9245D5DDA76ED395 ON b_provider_education');
        $this->addSql('DROP INDEX `primary` ON b_provider_education');
        $this->addSql('ALTER TABLE b_provider_experience DROP FOREIGN KEY FK_95669319A76ED395');
        $this->addSql('DROP INDEX IDX_95669319A76ED395 ON b_provider_experience');
        $this->addSql('DROP INDEX `primary` ON b_provider_experience');
        $this->addSql('ALTER TABLE b_provider_insurance DROP FOREIGN KEY FK_2D412443A76ED395');
        $this->addSql('DROP INDEX IDX_2D412443A76ED395 ON b_provider_insurance');
        $this->addSql('DROP INDEX `primary` ON b_provider_insurance');
        $this->addSql('ALTER TABLE b_provider_license DROP FOREIGN KEY FK_421ABE29A76ED395');
        $this->addSql('DROP INDEX IDX_421ABE29A76ED395 ON b_provider_license');
        $this->addSql('DROP INDEX `primary` ON b_provider_license');
        $this->addSql('ALTER TABLE b_recruiter DROP INDEX UNIQ_13AF64A6A76ED395, ADD INDEX idx_user_id (user_id)');
        $this->addSql('ALTER TABLE b_recruiter DROP FOREIGN KEY FK_13AF64A6A76ED395');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_recruiter
            CHANGE
              speciality speciality VARCHAR(255) DEFAULT NULL COMMENT 'e.g., Locum Agency, Freelancer',
            CHANGE
              membership_level membership_level VARCHAR(50) DEFAULT 'Silver' COMMENT 'Silver, Gold, Diamond',
            CHANGE
              rating rating NUMERIC(3, 2) DEFAULT '0.00',
            CHANGE
              is_verified is_verified TINYINT(1) DEFAULT 0
        SQL);
        $this->addSql('ALTER TABLE b_review DROP FOREIGN KEY FK_EAF0C194A53A8AA');
        $this->addSql('ALTER TABLE b_review DROP FOREIGN KEY FK_EAF0C19441CD9E7A');
        $this->addSql('ALTER TABLE b_review DROP FOREIGN KEY FK_EAF0C1943E030ACD');
        $this->addSql('DROP INDEX IDX_EAF0C194A53A8AA ON b_review');
        $this->addSql('DROP INDEX IDX_EAF0C19441CD9E7A ON b_review');
        $this->addSql('DROP INDEX IDX_EAF0C1943E030ACD ON b_review');
        $this->addSql('DROP INDEX `primary` ON b_review');
        $this->addSql('ALTER TABLE b_speciality DROP FOREIGN KEY FK_44A7D5E2FDEF8996');
        $this->addSql('DROP INDEX IDX_44A7D5E2FDEF8996 ON b_speciality');
        $this->addSql('DROP INDEX `primary` ON b_speciality');
        $this->addSql('ALTER TABLE b_user DROP FOREIGN KEY FK_E26A5EBD41CD9E7A');
        $this->addSql('ALTER TABLE b_user DROP FOREIGN KEY FK_E26A5EBD156BE243');
        $this->addSql('DROP INDEX IDX_E26A5EBD41CD9E7A ON b_user');
        $this->addSql('DROP INDEX IDX_E26A5EBD156BE243 ON b_user');
        $this->addSql('DROP INDEX `primary` ON b_user');
        $this->addSql('DROP INDEX UNIQ_IDENTIFIER_EMAIL ON b_user');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              b_user
            ADD
              profile_picture VARCHAR(255) DEFAULT NULL,
            ADD
              provider_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            CHANGE
              roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`,
            CHANGE
              oauth_data oauth_data LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`
        SQL);
        $this->addSql('ALTER TABLE b_workflow_log DROP FOREIGN KEY FK_26543080FC2869F0');
        $this->addSql('DROP INDEX IDX_26543080FC2869F0 ON b_workflow_log');
        $this->addSql('DROP INDEX `primary` ON b_workflow_log');
        $this->addSql('DROP INDEX IDX_75EA56E016BA31DB ON messenger_messages');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              messenger_messages
            ADD
              queue VARCHAR(190) DEFAULT 'default' NOT NULL,
            CHANGE
              queue_name queue_name VARCHAR(190) DEFAULT 'default' NOT NULL,
            CHANGE
              created_at created_at DATETIME NOT NULL,
            CHANGE
              available_at available_at DATETIME DEFAULT NULL,
            CHANGE
              delivered_at delivered_at DATETIME DEFAULT NULL
        SQL);
        $this->addSql('CREATE INDEX IDX_MESSENGER_QUEUE ON messenger_messages (queue)');
        $this->addSql('ALTER TABLE messenger_messages RENAME INDEX idx_75ea56e0e3bd61ce TO IDX_MESSENGER_AVAILABLE_AT');
        $this->addSql('ALTER TABLE provider_speciality DROP FOREIGN KEY FK_D661EC36A53A8AA');
        $this->addSql('ALTER TABLE provider_speciality DROP FOREIGN KEY FK_D661EC363B5A08D7');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              provider_speciality
            CHANGE
              provider_id provider_id BINARY(16) NOT NULL,
            CHANGE
              speciality_id speciality_id BINARY(16) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              provider_speciality RENAME INDEX idx_d661ec36a53a8aa TO IDX_PROVIDER_SPECIALITY_PROVIDER
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              provider_speciality RENAME INDEX idx_d661ec363b5a08d7 TO IDX_PROVIDER_SPECIALITY_SPECIALITY
        SQL);
        $this->addSql('ALTER TABLE to_do DROP FOREIGN KEY FK_1249EDA0A53A8AA');
        $this->addSql('ALTER TABLE to_do DROP FOREIGN KEY FK_1249EDA041CD9E7A');
        $this->addSql('ALTER TABLE to_do DROP FOREIGN KEY FK_1249EDA0156BE243');
        $this->addSql('ALTER TABLE to_do DROP FOREIGN KEY FK_1249EDA092741D25');
        $this->addSql('ALTER TABLE to_do DROP FOREIGN KEY FK_1249EDA0BE04EA9');
        $this->addSql('ALTER TABLE to_do DROP FOREIGN KEY FK_1249EDA0E3BD13F3');
        $this->addSql('DROP INDEX IDX_1249EDA0156BE243 ON to_do');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              to_do
            DROP
              recruiter_id,
            CHANGE
              id id BINARY(16) NOT NULL,
            CHANGE
              provider_id provider_id BINARY(16) NOT NULL,
            CHANGE
              employer_id employer_id BINARY(16) NOT NULL,
            CHANGE
              bookmark_id bookmark_id BINARY(16) DEFAULT NULL,
            CHANGE
              job_id job_id BINARY(16) DEFAULT NULL,
            CHANGE
              document_request_id document_request_id BINARY(16) DEFAULT NULL,
            CHANGE
              description description TEXT DEFAULT NULL,
            CHANGE
              is_completed is_completed TINYINT(1) DEFAULT 0 NOT NULL
        SQL);
        $this->addSql('CREATE INDEX IDX_to_do_is_completed ON to_do (is_completed)');
        $this->addSql('ALTER TABLE to_do RENAME INDEX idx_1249eda0a53a8aa TO IDX_to_do_provider_id');
        $this->addSql('ALTER TABLE to_do RENAME INDEX idx_1249eda041cd9e7a TO IDX_to_do_employer_id');
        $this->addSql('ALTER TABLE to_do RENAME INDEX idx_1249eda0e3bd13f3 TO IDX_to_do_document_request_id');
        $this->addSql('ALTER TABLE to_do RENAME INDEX idx_1249eda092741d25 TO IDX_to_do_bookmark_id');
        $this->addSql('ALTER TABLE to_do RENAME INDEX idx_1249eda0be04ea9 TO IDX_to_do_job_id');
    }
}

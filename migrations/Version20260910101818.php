<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260910101818 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: users, subscriptions, usage_counters, resumes, job_offers, tailorings';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE job_offers (id BINARY(16) NOT NULL, url LONGTEXT DEFAULT NULL, url_hash VARCHAR(64) DEFAULT NULL, raw_text LONGTEXT NOT NULL, title VARCHAR(255) DEFAULT NULL, company VARCHAR(255) DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, language VARCHAR(5) DEFAULT NULL, analysis JSON DEFAULT NULL, fetch_status VARCHAR(255) NOT NULL, fetched_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_job_offers_url_hash (url_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE resumes (id BINARY(16) NOT NULL, title VARCHAR(120) NOT NULL, data JSON NOT NULL, schema_version INT NOT NULL, language VARCHAR(5) NOT NULL, source_file VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id BINARY(16) NOT NULL, INDEX IDX_CDB8AD33A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE subscriptions (id BINARY(16) NOT NULL, plan VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, stripe_customer_id VARCHAR(255) DEFAULT NULL, stripe_subscription_id VARCHAR(255) DEFAULT NULL, current_period_end DATETIME DEFAULT NULL, updated_at DATETIME NOT NULL, user_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_4778A01A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tailorings (id BINARY(16) NOT NULL, status VARCHAR(255) NOT NULL, tailored_data JSON DEFAULT NULL, match_score SMALLINT DEFAULT NULL, changes JSON DEFAULT NULL, theme VARCHAR(30) NOT NULL, error_message LONGTEXT DEFAULT NULL, llm_usage JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, user_id BINARY(16) NOT NULL, resume_id BINARY(16) NOT NULL, job_offer_id BINARY(16) DEFAULT NULL, INDEX IDX_49E5C817A76ED395 (user_id), INDEX IDX_49E5C817D262AF09 (resume_id), INDEX IDX_49E5C8173481D195 (job_offer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE usage_counters (id BINARY(16) NOT NULL, period_key VARCHAR(7) NOT NULL, tailorings_count INT NOT NULL, user_id BINARY(16) NOT NULL, UNIQUE INDEX uniq_usage_user_period (user_id, period_key), INDEX IDX_C8479D12A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id BINARY(16) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, locale VARCHAR(2) NOT NULL, email_verified_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_users_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE resumes ADD CONSTRAINT FK_CDB8AD33A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_4778A01A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tailorings ADD CONSTRAINT FK_49E5C817A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tailorings ADD CONSTRAINT FK_49E5C817D262AF09 FOREIGN KEY (resume_id) REFERENCES resumes (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tailorings ADD CONSTRAINT FK_49E5C8173481D195 FOREIGN KEY (job_offer_id) REFERENCES job_offers (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE usage_counters ADD CONSTRAINT FK_C8479D12A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE resumes DROP FOREIGN KEY FK_CDB8AD33A76ED395');
        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY FK_4778A01A76ED395');
        $this->addSql('ALTER TABLE tailorings DROP FOREIGN KEY FK_49E5C817A76ED395');
        $this->addSql('ALTER TABLE tailorings DROP FOREIGN KEY FK_49E5C817D262AF09');
        $this->addSql('ALTER TABLE tailorings DROP FOREIGN KEY FK_49E5C8173481D195');
        $this->addSql('ALTER TABLE usage_counters DROP FOREIGN KEY FK_C8479D12A76ED395');
        $this->addSql('DROP TABLE job_offers');
        $this->addSql('DROP TABLE resumes');
        $this->addSql('DROP TABLE subscriptions');
        $this->addSql('DROP TABLE tailorings');
        $this->addSql('DROP TABLE usage_counters');
        $this->addSql('DROP TABLE users');
    }
}

<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The tables of cyllene-digital/ai-translation-bundle. The bundle ships its entity
 * mapping, not migrations: like any host application, the test application versions
 * them itself (generated with doctrine:migrations:diff, MySQL/MariaDB flavour).
 */
final class Version20260923120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the AI translation bundle tables (overrides, suggestions, scope parameters, generation log)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'These migrations are written for MySQL and MariaDB. On another database, create the schema from the mapping: doctrine:schema:update --force.',
        );

        $this->addSql('CREATE TABLE cyllene_translation_generation_log (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, catalogue VARCHAR(255) NOT NULL, target_locale VARCHAR(10) NOT NULL, source_locale VARCHAR(10) NOT NULL, provider VARCHAR(50) NOT NULL, suggestions_created INT NOT NULL, error_message LONGTEXT DEFAULT NULL, scope VARCHAR(64) DEFAULT \'\' NOT NULL, INDEX idx_generation_log_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cyllene_translation_suggestion (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, pending_key VARCHAR(32) DEFAULT NULL, metadata JSON DEFAULT NULL, generation_error LONGTEXT DEFAULT NULL, reviewed_by VARCHAR(255) DEFAULT NULL, reviewed_at DATETIME DEFAULT NULL, translation_key VARCHAR(400) NOT NULL, catalogue VARCHAR(255) NOT NULL, locale VARCHAR(10) NOT NULL, suggested_value LONGTEXT DEFAULT NULL, source_value LONGTEXT NOT NULL, source_locale VARCHAR(10) NOT NULL, provider VARCHAR(50) NOT NULL, confidence DOUBLE PRECISION NOT NULL, scope VARCHAR(64) DEFAULT \'\' NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, INDEX idx_translation_suggestion_status (status), INDEX idx_translation_suggestion_locale_catalogue (locale, catalogue), INDEX idx_translation_suggestion_scope (scope), UNIQUE INDEX uniq_translation_suggestion_pending (pending_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cyllene_translation_scope_parameters (prompt_context LONGTEXT DEFAULT NULL, scope VARCHAR(64) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (scope)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cyllene_translation_override (id INT AUTO_INCREMENT NOT NULL, value LONGTEXT NOT NULL, original_value LONGTEXT DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, translation_key VARCHAR(400) NOT NULL, catalogue VARCHAR(255) NOT NULL, locale VARCHAR(10) NOT NULL, scope VARCHAR(64) DEFAULT \'\' NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, INDEX idx_translation_override_locale_catalogue (locale, catalogue), INDEX idx_translation_override_locale (locale), UNIQUE INDEX uniq_translation_override_key (locale, catalogue, translation_key, scope), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cyllene_translation_generation_log');
        $this->addSql('DROP TABLE cyllene_translation_suggestion');
        $this->addSql('DROP TABLE cyllene_translation_scope_parameters');
        $this->addSql('DROP TABLE cyllene_translation_override');
    }
}

<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Follows the engine's mapping as of cyllene-digital/ai-translation-bundle 1.0.0: timestamps NOT NULL (set at construction), provider names capped at
 * 64 characters, scopes and locales compared byte for byte too, the NO PAD binary
 * collation on MariaDB ("a" ≠ "a "), and the redundant indexes replaced by one matching
 * the suggestion queries. Generated with doctrine:schema:update --dump-sql, MariaDB flavour.
 */
final class Version20261002120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make the AI translation timestamps NOT NULL, compare scopes and locales byte for byte, and refit the indexes';
    }

    public function up(Schema $schema): void
    {
        // The NO PAD binary collation is not the same one on MariaDB and on MySQL, and the
        // platform does not tell them apart when a MariaDB is declared with a bare version.
        $version = $this->connection->fetchOne('SELECT VERSION()');
        $collation = \is_string($version) && str_contains($version, 'MariaDB') ? 'utf8mb4_nopad_bin' : 'utf8mb4_0900_bin';

        // Rows written before the engine set the dates at construction may lack them.
        foreach (['cyllene_translation_suggestion', 'cyllene_translation_scope_parameters', 'cyllene_translation_override'] as $table) {
            $this->addSql(\sprintf('UPDATE %s SET created_at = COALESCE(created_at, NOW()), updated_at = COALESCE(updated_at, created_at, NOW()) WHERE created_at IS NULL OR updated_at IS NULL', $table));
        }

        $this->addSql('ALTER TABLE cyllene_translation_generation_log CHANGE provider provider VARCHAR(64) NOT NULL');
        $this->addSql('DROP INDEX idx_translation_suggestion_locale_catalogue ON cyllene_translation_suggestion');
        $this->addSql('DROP INDEX idx_translation_suggestion_scope ON cyllene_translation_suggestion');
        $this->addSql('DROP INDEX idx_translation_suggestion_status ON cyllene_translation_suggestion');
        $this->addSql("ALTER TABLE cyllene_translation_suggestion CHANGE locale locale VARCHAR(32) NOT NULL COLLATE `{$collation}`, CHANGE catalogue catalogue VARCHAR(255) NOT NULL COLLATE `{$collation}`, CHANGE translation_key translation_key VARCHAR(400) NOT NULL COLLATE `{$collation}`, CHANGE provider provider VARCHAR(64) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE scope scope VARCHAR(64) DEFAULT '' NOT NULL COLLATE `{$collation}`");
        $this->addSql('CREATE INDEX idx_translation_suggestion_status_lookup ON cyllene_translation_suggestion (status, locale, catalogue, scope)');
        $this->addSql("ALTER TABLE cyllene_translation_scope_parameters CHANGE scope scope VARCHAR(64) NOT NULL COLLATE `{$collation}`, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL");
        $this->addSql('DROP INDEX idx_translation_override_locale ON cyllene_translation_override');
        $this->addSql('DROP INDEX idx_translation_override_locale_catalogue ON cyllene_translation_override');
        $this->addSql("ALTER TABLE cyllene_translation_override CHANGE locale locale VARCHAR(32) NOT NULL COLLATE `{$collation}`, CHANGE catalogue catalogue VARCHAR(255) NOT NULL COLLATE `{$collation}`, CHANGE translation_key translation_key VARCHAR(400) NOT NULL COLLATE `{$collation}`, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE scope scope VARCHAR(64) DEFAULT '' NOT NULL COLLATE `{$collation}`");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE cyllene_translation_override CHANGE locale locale VARCHAR(32) NOT NULL, CHANGE catalogue catalogue VARCHAR(255) NOT NULL COLLATE `utf8mb4_bin`, CHANGE translation_key translation_key VARCHAR(400) NOT NULL COLLATE `utf8mb4_bin`, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE scope scope VARCHAR(64) DEFAULT '' NOT NULL");
        $this->addSql('CREATE INDEX idx_translation_override_locale_catalogue ON cyllene_translation_override (locale, catalogue)');
        $this->addSql('CREATE INDEX idx_translation_override_locale ON cyllene_translation_override (locale)');
        $this->addSql('ALTER TABLE cyllene_translation_scope_parameters CHANGE scope scope VARCHAR(64) NOT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('DROP INDEX idx_translation_suggestion_status_lookup ON cyllene_translation_suggestion');
        $this->addSql("ALTER TABLE cyllene_translation_suggestion CHANGE locale locale VARCHAR(32) NOT NULL, CHANGE catalogue catalogue VARCHAR(255) NOT NULL COLLATE `utf8mb4_bin`, CHANGE translation_key translation_key VARCHAR(400) NOT NULL COLLATE `utf8mb4_bin`, CHANGE provider provider VARCHAR(50) NOT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE scope scope VARCHAR(64) DEFAULT '' NOT NULL");
        $this->addSql('CREATE INDEX idx_translation_suggestion_status ON cyllene_translation_suggestion (status)');
        $this->addSql('CREATE INDEX idx_translation_suggestion_scope ON cyllene_translation_suggestion (scope)');
        $this->addSql('CREATE INDEX idx_translation_suggestion_locale_catalogue ON cyllene_translation_suggestion (locale, catalogue)');
        $this->addSql('ALTER TABLE cyllene_translation_generation_log CHANGE provider provider VARCHAR(50) NOT NULL');
    }
}

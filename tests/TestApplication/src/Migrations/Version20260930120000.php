<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Follows the engine's mapping update (cyllene-digital/ai-translation-bundle 1009d72):
 * locale columns widened to 32 characters, and translation keys / catalogues compared
 * case-sensitively on MySQL/MariaDB ("Shop/messages" ≠ "shop/messages") through a
 * binary collation. Generated with doctrine:migrations:diff, MySQL/MariaDB flavour.
 */
final class Version20260930120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen the AI translation locale columns and make keys and catalogues case-sensitive';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cyllene_translation_generation_log CHANGE target_locale target_locale VARCHAR(32) NOT NULL, CHANGE source_locale source_locale VARCHAR(32) NOT NULL');
        $this->addSql('ALTER TABLE cyllene_translation_suggestion CHANGE locale locale VARCHAR(32) NOT NULL, CHANGE catalogue catalogue VARCHAR(255) NOT NULL COLLATE `utf8mb4_bin`, CHANGE translation_key translation_key VARCHAR(400) NOT NULL COLLATE `utf8mb4_bin`, CHANGE source_locale source_locale VARCHAR(32) NOT NULL');
        $this->addSql('ALTER TABLE cyllene_translation_override CHANGE locale locale VARCHAR(32) NOT NULL, CHANGE catalogue catalogue VARCHAR(255) NOT NULL COLLATE `utf8mb4_bin`, CHANGE translation_key translation_key VARCHAR(400) NOT NULL COLLATE `utf8mb4_bin`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cyllene_translation_generation_log CHANGE target_locale target_locale VARCHAR(10) NOT NULL, CHANGE source_locale source_locale VARCHAR(10) NOT NULL');
        $this->addSql('ALTER TABLE cyllene_translation_suggestion CHANGE locale locale VARCHAR(10) NOT NULL, CHANGE catalogue catalogue VARCHAR(255) NOT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE translation_key translation_key VARCHAR(400) NOT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE source_locale source_locale VARCHAR(10) NOT NULL');
        $this->addSql('ALTER TABLE cyllene_translation_override CHANGE locale locale VARCHAR(10) NOT NULL, CHANGE catalogue catalogue VARCHAR(255) NOT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE translation_key translation_key VARCHAR(400) NOT NULL COLLATE `utf8mb4_unicode_ci`');
    }
}

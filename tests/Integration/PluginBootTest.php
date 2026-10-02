<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Integration;

use CylleneDigital\AiTranslationBundle\Bridge\DeeplProvider;
use CylleneDigital\AiTranslationBundle\Bridge\DefaultProvider;
use CylleneDigital\AiTranslationBundle\Override\OverrideAwareTranslator;
use CylleneDigital\AiTranslationBundle\Provider\TranslationProviderRegistry;
use CylleneDigital\AiTranslationBundle\Suggestion\TranslationAiService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Boots the Sylius test application with the plugin enabled and checks the wiring —
 * no database needed. This is the test that would have caught the compile-time
 * regressions found during the first manual BO run (extension alias, provider
 * definitions, translator decoration).
 */
final class PluginBootTest extends KernelTestCase
{
    public function testTheProvidersDeclaredInTheConfigAreBuiltAndRegistered(): void
    {
        self::bootKernel();

        /** @var TranslationProviderRegistry $registry */
        $registry = self::getContainer()->get(TranslationProviderRegistry::class);

        // The committed test-app config declares these two; a developer's local copy may
        // reorder them, add more, or point default_provider elsewhere — the wiring under
        // test must hold regardless of those local choices.
        self::assertContains('llm', $registry->getNames());
        self::assertContains('deepl', $registry->getNames());
        self::assertInstanceOf(DefaultProvider::class, $registry->get('llm'));
        self::assertInstanceOf(DeeplProvider::class, $registry->get('deepl'));

        // The configured default_provider wins when no name is given — whichever it is.
        /** @var string $default */
        $default = self::getContainer()->getParameter('cyllene_digital_ai_translation.default_provider');
        self::assertSame($registry->get($default), $registry->get());
    }

    public function testTheTranslatorIsDecorated(): void
    {
        self::bootKernel();

        self::assertInstanceOf(
            OverrideAwareTranslator::class,
            self::getContainer()->get(TranslatorInterface::class),
        );
    }

    public function testTheAiServiceIsWired(): void
    {
        self::bootKernel();

        self::assertInstanceOf(TranslationAiService::class, self::getContainer()->get(TranslationAiService::class));
    }
}

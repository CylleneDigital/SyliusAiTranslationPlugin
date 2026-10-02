<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\AiTranslationBundle\Scope\ScopeParametersManager;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Context\Setup\TranslationContext;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Context\Ui\Admin\DescribingChannelsToTheAiContext;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Context\Ui\Admin\ManagingTranslationsContext;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Context\Ui\Admin\ReviewingSuggestionsContext;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Context\Ui\Shop\ReadingTranslationsContext;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Element\Admin\Channel\PromptContextFormElement;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Page\Admin\Suggestion\IndexPage as SuggestionIndexPage;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Page\Admin\Translation\IndexPage as TranslationIndexPage;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Page\Shop\HomePage;

/*
 * Behat pages and contexts, imported by tests/TestApplication/config/services_test.php in the
 * "test" environment. Behat resolves the contexts from the container, so they are public.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->public();

    $services->set('cyllene_digital_sylius_ai_translation.behat.page.admin.translation.index', TranslationIndexPage::class)
        ->parent('sylius.behat.symfony_page')
        ->private();

    $services->set('cyllene_digital_sylius_ai_translation.behat.page.admin.suggestion.index', SuggestionIndexPage::class)
        ->parent('sylius.behat.symfony_page')
        ->private();

    $services->set('cyllene_digital_sylius_ai_translation.behat.element.admin.channel.prompt_context_form', PromptContextFormElement::class)
        ->parent('sylius.behat.element')
        ->private();

    $services->set('cyllene_digital_sylius_ai_translation.behat.context.ui.admin.describing_channels_to_the_ai', DescribingChannelsToTheAiContext::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.behat.element.admin.channel.prompt_context_form'),
            service(ScopeParametersManager::class),
            service('doctrine.orm.default_entity_manager'),
        ]);

    $services->set('cyllene_digital_sylius_ai_translation.behat.page.shop.home', HomePage::class)
        ->parent('sylius.behat.symfony_page')
        ->private();

    $services->set('cyllene_digital_sylius_ai_translation.behat.context.ui.shop.reading_translations', ReadingTranslationsContext::class)
        ->args([service('cyllene_digital_sylius_ai_translation.behat.page.shop.home')]);

    $services->set('cyllene_digital_sylius_ai_translation.behat.context.setup.translation', TranslationContext::class)
        ->args([
            service('doctrine.orm.default_entity_manager'),
            service('sylius.factory.admin_user'),
            service('sylius.behat.admin_security'),
            service(TranslationManagerInterface::class),
            service('cache.app'),
        ]);

    $services->set('cyllene_digital_sylius_ai_translation.behat.context.ui.admin.managing_translations', ManagingTranslationsContext::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.behat.page.admin.translation.index'),
            service(TranslationManagerInterface::class),
        ]);

    $services->set('cyllene_digital_sylius_ai_translation.behat.context.ui.admin.reviewing_suggestions', ReviewingSuggestionsContext::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.behat.page.admin.suggestion.index'),
            service(TranslationManagerInterface::class),
            service('doctrine.orm.default_entity_manager'),
        ]);
};

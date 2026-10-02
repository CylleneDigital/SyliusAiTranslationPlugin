<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\AiTranslationBundle\Override\OverrideReader;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\AiTranslationBundle\Override\TranslationValueValidator;
use CylleneDigital\AiTranslationBundle\Repository\TranslationSuggestionRepository;
use CylleneDigital\AiTranslationBundle\Suggestion\PlaceholderConsistencyChecker;
use CylleneDigital\SyliusAiTranslationPlugin\Override\ManualOverrideEditor;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('cyllene_digital_sylius_ai_translation.override.manual_editor', ManualOverrideEditor::class)
        ->args([
            service(TranslationManagerInterface::class),
            service(OverrideReader::class),
            service(TranslationSuggestionRepository::class),
            service(TranslationValueValidator::class),
            service(PlaceholderConsistencyChecker::class),
        ]);
};

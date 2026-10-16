<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\AiTranslationBundle\Override\OverrideReader;
use CylleneDigital\AiTranslationBundle\Override\TranslationValueValidator;
use CylleneDigital\AiTranslationBundle\Suggestion\PlaceholderConsistencyChecker;
use CylleneDigital\SyliusAiTranslationPlugin\Twig\SuggestionReviewExtension;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('cyllene_digital_sylius_ai_translation.twig.suggestion_review_extension', SuggestionReviewExtension::class)
        ->args([
            service(OverrideReader::class),
            service(PlaceholderConsistencyChecker::class),
            service(TranslationValueValidator::class),
        ])
        ->tag('twig.extension')
        ->tag('kernel.reset', ['method' => 'reset']);
};

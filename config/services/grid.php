<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\BulkActionGridRenderer;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\CatalogueGrid;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\CatalogueGridProvider;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\CatalogueRows;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\GridChoiceProvider;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\GridViewBuilder;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\SuggestionGrid;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('cyllene_digital_sylius_ai_translation.grid.catalogue_rows', CatalogueRows::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.view.translation_view_factory'),
            service(TranslationManagerInterface::class),
            service('cyllene_digital_sylius_ai_translation.scope.channel_resolver'),
        ]);

    $services->set('cyllene_digital_sylius_ai_translation.grid.choice_provider', GridChoiceProvider::class)
        ->args([
            service(TranslationManagerInterface::class),
            service('cyllene_digital_sylius_ai_translation.scope.channel_resolver'),
            service('sylius.converter.locale'),
            service('translator'),
        ]);

    $services->set('cyllene_digital_sylius_ai_translation.grid.view_builder', GridViewBuilder::class)
        ->args([
            service('sylius.grid.provider'),
            service('sylius.grid.view_factory'),
        ]);

    $services->set('cyllene_digital_sylius_ai_translation.grid.catalogue', CatalogueGrid::class)
        ->args([
            service('request_stack'),
            service('cyllene_digital_sylius_ai_translation.scope.channel_resolver'),
            service('cyllene_digital_sylius_ai_translation.grid.choice_provider'),
            service('cyllene_digital_sylius_ai_translation.grid.catalogue_rows'),
            service('translator'),
            service('security.authorization_checker'),
        ])
        ->tag('sylius.grid');

    // The catalogue grid names its data provider by this id (CatalogueGrid's #[AsGrid]).
    $services->set(CatalogueGridProvider::SERVICE_ID, CatalogueGridProvider::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.grid.catalogue_rows'),
            service('cyllene_digital_sylius_ai_translation.scope.channel_resolver'),
        ])
        ->tag('sylius.grid_data_provider');

    $services->set('cyllene_digital_sylius_ai_translation.grid.suggestion', SuggestionGrid::class)
        ->args([
            service('request_stack'),
            service('cyllene_digital_sylius_ai_translation.scope.channel_resolver'),
            service('cyllene_digital_sylius_ai_translation.grid.choice_provider'),
            service('security.authorization_checker'),
        ])
        ->tag('sylius.grid');

    // Outermost in the chain: restores the plain-GridView fallback the resource bundle's
    // bulk-action renderer lacks (see the class).
    $services->set('cyllene_digital_sylius_ai_translation.grid.bulk_action_renderer', BulkActionGridRenderer::class)
        ->decorate('sylius.grid.bulk_action_renderer.twig')
        ->args([
            service('.inner'),
            service('twig'),
            param('sylius.grid.templates.bulk_action'),
        ]);
};

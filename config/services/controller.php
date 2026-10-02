<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\AiTranslationBundle\Coverage\CoverageCalculator;
use CylleneDigital\AiTranslationBundle\Estimation\GenerationCostEstimator;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\AiTranslationBundle\Provider\TranslationProviderRegistry;
use CylleneDigital\AiTranslationBundle\Repository\TranslationSuggestionRepository;
use CylleneDigital\AiTranslationBundle\Suggestion\TranslationAiServiceInterface;
use CylleneDigital\AiTranslationBundle\Transfer\OverrideExporter;
use CylleneDigital\AiTranslationBundle\Transfer\OverrideImporter;
use CylleneDigital\AiTranslationBundle\Transfer\ViewExporter;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Screen;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Suggestion;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Translation;
use CylleneDigital\SyliusAiTranslationPlugin\DependencyInjection\CylleneDigitalSyliusAiTranslationExtension;

/*
 * One controller per route, each keeping its class as id: config/routes/admin.yaml names them
 * that way. Their routes live there rather than in attributes: a controller tagged
 * `routing.controller` would have its routes registered a second time by the host's own import,
 * outside the admin prefix.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // What the actions of a screen share: its CSRF intention (the one its templates ask a token
    // for) and the way back to its list.
    $services->set('cyllene_digital_sylius_ai_translation.controller.translation_screen', Screen::class)
        ->args([
            service('security.csrf.token_manager'),
            service('router'),
            'cyllene_digital_sylius_ai_translation_transfer',
            'cyllene_digital_sylius_ai_translation_admin_translation_index',
        ]);

    $services->set('cyllene_digital_sylius_ai_translation.controller.suggestion_screen', Screen::class)
        ->args([
            service('security.csrf.token_manager'),
            service('router'),
            'cyllene_digital_sylius_ai_translation_suggestion',
            'cyllene_digital_sylius_ai_translation_admin_suggestion_index',
        ]);

    $services->set('cyllene_digital_sylius_ai_translation.controller.row_scope_resolver', Translation\RowScopeResolver::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.scope.channel_resolver'),
            service(TranslationManagerInterface::class),
        ]);

    $services->set('cyllene_digital_sylius_ai_translation.controller.generation_run_validator', Suggestion\GenerationRunValidator::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.scope.channel_resolver'),
            service(TranslationManagerInterface::class),
            service(TranslationProviderRegistry::class),
        ]);

    $services->set('cyllene_digital_sylius_ai_translation.controller.pending_suggestion_selector', Suggestion\PendingSuggestionSelector::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.scope.channel_resolver'),
            service(TranslationSuggestionRepository::class),
        ]);

    // Catalogue screen.
    $services->set(Translation\IndexController::class)
        ->args([
            service('twig'),
            service('cyllene_digital_sylius_ai_translation.scope.channel_resolver'),
            service(TranslationManagerInterface::class),
            service(TranslationSuggestionRepository::class),
            service(CoverageCalculator::class),
            service('cyllene_digital_sylius_ai_translation.grid.view_builder'),
            service('cyllene_digital_sylius_ai_translation.transfer.import_stager'),
        ])
        ->tag('controller.service_arguments');

    $services->set(Translation\SaveController::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.controller.translation_screen'),
            service('cyllene_digital_sylius_ai_translation.controller.row_scope_resolver'),
            service('cyllene_digital_sylius_ai_translation.override.manual_editor'),
            service('translator'),
        ])
        ->tag('controller.service_arguments');

    $services->set(Translation\ResetController::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.controller.translation_screen'),
            service('cyllene_digital_sylius_ai_translation.controller.row_scope_resolver'),
            service(TranslationManagerInterface::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(Translation\PurgeOrphansController::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.controller.translation_screen'),
            service(TranslationManagerInterface::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(Translation\ExportController::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.controller.translation_screen'),
            service(OverrideExporter::class),
            service('cyllene_digital_sylius_ai_translation.scope.channel_resolver'),
            service('cyllene_digital_sylius_ai_translation.grid.catalogue_rows'),
            service(ViewExporter::class),
            service('clock'),
        ])
        ->tag('controller.service_arguments');

    $services->set(Translation\ImportTemplateController::class)
        ->tag('controller.service_arguments');

    $services->set(Translation\ImportController::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.controller.translation_screen'),
            service('cyllene_digital_sylius_ai_translation.transfer.import_stager'),
            service('validator'),
        ])
        ->tag('controller.service_arguments');

    $services->set(Translation\ImportConfirmController::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.controller.translation_screen'),
            service('cyllene_digital_sylius_ai_translation.transfer.import_stager'),
            service(OverrideImporter::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(Translation\ImportCancelController::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.controller.translation_screen'),
            service('cyllene_digital_sylius_ai_translation.transfer.import_stager'),
        ])
        ->tag('controller.service_arguments');

    // Suggestions screen.
    $services->set(Suggestion\IndexController::class)
        ->args([
            service('twig'),
            service('cyllene_digital_sylius_ai_translation.scope.channel_resolver'),
            service('cyllene_digital_sylius_ai_translation.grid.view_builder'),
            service(TranslationSuggestionRepository::class),
            service(TranslationProviderRegistry::class),
            service(TranslationManagerInterface::class),
            service('cyllene_digital_sylius_ai_translation.grid.choice_provider'),
            service('doctrine'),
            param('cyllene_digital_ai_translation.default_provider'),
        ])
        ->tag('controller.service_arguments');

    $services->set(Suggestion\EstimateController::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.scope.channel_resolver'),
            service('cyllene_digital_sylius_ai_translation.controller.generation_run_validator'),
            service(GenerationCostEstimator::class),
            service('translator'),
        ])
        ->tag('controller.service_arguments');

    $services->set(Suggestion\GenerateController::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.controller.suggestion_screen'),
            service('cyllene_digital_sylius_ai_translation.scope.channel_resolver'),
            service('cyllene_digital_sylius_ai_translation.controller.generation_run_validator'),
            service(TranslationManagerInterface::class),
            // Not the host's default bus: Sylius' one is transactional, and a failing run
            // would roll back the suggestions already billed (see the extension).
            service(CylleneDigitalSyliusAiTranslationExtension::GENERATION_BUS),
        ])
        ->tag('controller.service_arguments');

    $services->set(Suggestion\ApproveController::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.controller.suggestion_screen'),
            service(TranslationAiServiceInterface::class),
            service('translator'),
        ])
        ->tag('controller.service_arguments');

    $services->set(Suggestion\RejectController::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.controller.suggestion_screen'),
            service(TranslationAiServiceInterface::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(Suggestion\ApproveBulkController::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.controller.suggestion_screen'),
            service('cyllene_digital_sylius_ai_translation.controller.pending_suggestion_selector'),
            service(TranslationAiServiceInterface::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(Suggestion\RejectBulkController::class)
        ->args([
            service('cyllene_digital_sylius_ai_translation.controller.suggestion_screen'),
            service('cyllene_digital_sylius_ai_translation.controller.pending_suggestion_selector'),
            service(TranslationAiServiceInterface::class),
        ])
        ->tag('controller.service_arguments');
};

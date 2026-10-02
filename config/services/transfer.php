<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\AiTranslationBundle\Transfer\OverrideImporter;
use CylleneDigital\SyliusAiTranslationPlugin\DependencyInjection\CylleneDigitalSyliusAiTranslationExtension;
use CylleneDigital\SyliusAiTranslationPlugin\Transfer\ImportStager;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('cyllene_digital_sylius_ai_translation.transfer.import_stager', ImportStager::class)
        ->args([
            service(OverrideImporter::class),
            service(CylleneDigitalSyliusAiTranslationExtension::IMPORT_STAGING_POOL),
        ]);
};

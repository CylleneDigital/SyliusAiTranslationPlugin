<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\AiTranslationBundle\Scope\ScopeParametersManager;
use CylleneDigital\SyliusAiTranslationPlugin\Form\Extension\ChannelTypeExtension;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelType;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('cyllene_digital_sylius_ai_translation.form.extension.channel', ChannelTypeExtension::class)
        ->args([service(ScopeParametersManager::class)])
        ->tag('form.type_extension', ['extended_type' => ChannelType::class]);
};

<?php

declare(strict_types=1);

return [
    // The engine first: the plugin re-aliases its host-facing interfaces.
    CylleneDigital\AiTranslationBundle\CylleneDigitalAiTranslationBundle::class => ['all' => true],
    CylleneDigital\SyliusAiTranslationPlugin\CylleneDigitalSyliusAiTranslationPlugin::class => ['all' => true],
];

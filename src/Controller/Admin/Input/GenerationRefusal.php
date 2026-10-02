<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Input;

/** Why the coordinates of a generation run are refused, each with its message of the flashes domain. */
enum GenerationRefusal: string
{
    case UnknownScope = 'cyllene_digital_sylius_ai_translation.ui.generation_unknown_scope';
    case InvalidLocales = 'cyllene_digital_sylius_ai_translation.ui.generation_invalid_locales';
    case UnknownLocale = 'cyllene_digital_sylius_ai_translation.ui.generation_unknown_locale';
    case UnknownCatalogue = 'cyllene_digital_sylius_ai_translation.ui.generation_unknown_catalogue';
    case UnknownProvider = 'cyllene_digital_sylius_ai_translation.ui.generation_unknown_provider';
}

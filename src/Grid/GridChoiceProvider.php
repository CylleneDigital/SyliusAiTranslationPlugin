<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Grid;

use CylleneDigital\AiTranslationBundle\Catalogue\TranslationCatalogue;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelScopeResolver;
use Sylius\Component\Locale\Converter\LocaleConverterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The filter choices the two admin grids share: the catalogues as "Category › Domain —
 * Type" entries, and the locales labelled "[fr_FR] Français (France)" (the channel's
 * locales when a channel is selected).
 */
final readonly class GridChoiceProvider
{
    /** The criteria value prefix of a whole-category entry ("cat:shop"). */
    public const string CATEGORY_PREFIX = 'cat:';

    public function __construct(
        private TranslationManagerInterface $translationManager,
        private ChannelScopeResolver $scopeResolver,
        private LocaleConverterInterface $localeConverter,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<string, string> label => catalogue identifier
     */
    public function getCatalogueChoices(): array
    {
        $choices = [];

        foreach ($this->translationManager->getCatalogues() as $catalogue) {
            $choices[$this->describeCatalogue($catalogue)] = $catalogue->identifier;
        }

        ksort($choices);

        return $choices;
    }

    /**
     * The same catalogues, grouped by category (one optgroup each) - the generation form's
     * select, and the base of the catalogue filter's.
     *
     * @return array<string, array<string, string>> category label => (label => catalogue identifier)
     */
    public function getCatalogueChoicesByCategory(): array
    {
        $groups = [];

        foreach ($this->translationManager->getCatalogues() as $catalogue) {
            $groups[self::describeCategory($catalogue)][\sprintf('%s — %s', $catalogue->getDomainLabel(), ucfirst($catalogue->type))] = $catalogue->identifier;
        }

        ksort($groups);

        return $groups;
    }

    /**
     * The grouped catalogues with a leading "whole category" entry per group — its value is
     * the `cat:` prefix the catalogue rows resolve back to a category filter.
     *
     * @return array<string, array<string, string>> category label => (label => value)
     */
    public function getGroupedCatalogueChoices(): array
    {
        $wholeCategory = $this->translator->trans('cyllene_digital_sylius_ai_translation.ui.whole_category');
        $groups = [];

        foreach ($this->translationManager->getCatalogues() as $catalogue) {
            $groups[self::describeCategory($catalogue)] ??= [\sprintf('%s (%s)', $wholeCategory, $catalogue->category) => self::CATEGORY_PREFIX . $catalogue->category];
        }

        foreach ($this->getCatalogueChoicesByCategory() as $category => $choices) {
            $groups[$category] += $choices;
        }

        ksort($groups);

        return $groups;
    }

    /**
     * @return array<string, string> label => locale code
     */
    public function getLocaleChoices(string $scope, ?string $displayLocale): array
    {
        $choices = [];

        foreach ($this->scopeResolver->getLocales($scope) ?? $this->translationManager->getAvailableLocales() as $locale) {
            $name = $this->localeConverter->convertCodeToName($locale, $displayLocale);
            $choices[\sprintf('[%s] %s', $locale, mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1))] = $locale;
        }

        return $choices;
    }

    private function describeCatalogue(TranslationCatalogue $catalogue): string
    {
        return \sprintf('%s › %s — %s', self::describeCategory($catalogue), $catalogue->getDomainLabel(), ucfirst($catalogue->type));
    }

    private static function describeCategory(TranslationCatalogue $catalogue): string
    {
        return ucfirst(str_replace('_', ' ', $catalogue->category));
    }
}

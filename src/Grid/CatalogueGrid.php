<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Grid;

use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelScopeResolver;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use Sylius\Bundle\GridBundle\Builder\Action\Action;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\ItemActionGroup;
use Sylius\Bundle\GridBundle\Builder\Field\TwigField;
use Sylius\Bundle\GridBundle\Builder\Filter\BooleanFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\SelectFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\StringFilter;
use Sylius\Bundle\GridBundle\Grid\AbstractGrid;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\Builder\GridBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The catalogue browser as a Sylius grid over computed rows ({@see CatalogueGridProvider}):
 * one row per translation key of the browsed locale — untranslated ones included, with the
 * reference locale's text alongside — edited through the page's modal, never inline. A
 * host project extends it like any Sylius grid.
 *
 * Built at request time: the browsed locale and the channel decide the reference column
 * (hidden when the reference IS the browsed locale) and the locale filter's choices.
 */
#[AsGrid(name: self::NAME, provider: CatalogueGridProvider::SERVICE_ID)]
final class CatalogueGrid extends AbstractGrid
{
    public const string NAME = 'cyllene_digital_sylius_ai_translation_admin_catalogue';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ChannelScopeResolver $scopeResolver,
        private readonly GridChoiceProvider $choices,
        private readonly CatalogueRows $rows,
        private readonly TranslatorInterface $translator,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[\Override]
    public function buildGrid(GridBuilderInterface $gridBuilder): void
    {
        $request = $this->requestStack->getMainRequest();
        $scope = $this->scopeResolver->fromMainRequest();

        $criteria = GridQuery::criteria($request?->query->all() ?? []);
        $locale = $this->rows->resolveLocale($criteria, $scope);
        // The displayed reference cascades: the channel's default locale — unless it is
        // the very language being browsed, then the coverage default locale (so the channel
        // default's own gaps can be filled from the scoped page too). Nothing when the
        // browsed language IS the last reference standing.
        $referenceLocale = $this->scopeResolver->getReferenceLocale($scope);
        if ($referenceLocale === $locale) {
            $referenceLocale = $this->scopeResolver->getDefaultLocale();
        }

        // The locale select: the default locale IS the empty option ("Default — …"), so it
        // is stripped from the explicit choices — otherwise it would be offered twice.
        $localeChoices = $this->choices->getLocaleChoices($scope, $request?->getLocale());
        $defaultLocale = $this->rows->resolveLocale([], $scope);
        $defaultLabel = array_search($defaultLocale, $localeChoices, true);
        unset($localeChoices[(string) $defaultLabel]);

        $gridBuilder
            ->setLimits([100, 25, 50])
            ->addField(
                TwigField::create('key', '@CylleneDigitalSyliusAiTranslationPlugin/admin/translation/grid/field/key.html.twig')
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.translation_key')
                    ->setSortable(true)
                    ->setPath('.'),
            );

        if ($referenceLocale !== $locale) {
            $gridBuilder->addField(
                TwigField::create('reference', '@CylleneDigitalSyliusAiTranslationPlugin/admin/translation/grid/field/reference.html.twig')
                    // A literal, already-translated label: which locale the texts come from.
                    ->setLabel(\sprintf('%s [%s]', $this->translator->trans('cyllene_digital_sylius_ai_translation.ui.reference_column'), $referenceLocale))
                    ->setPath('.'),
            );
        }

        $gridBuilder
            ->addField(
                TwigField::create('original', '@CylleneDigitalSyliusAiTranslationPlugin/admin/translation/grid/field/original.html.twig')
                    ->setLabel(\sprintf('%s [%s]', $this->translator->trans('cyllene_digital_sylius_ai_translation.ui.original_value'), $locale))
                    ->setPath('.'),
            )
            ->addField(
                TwigField::create('current', '@CylleneDigitalSyliusAiTranslationPlugin/admin/translation/grid/field/current.html.twig')
                    ->setLabel(\sprintf('%s [%s]', $this->translator->trans('cyllene_digital_sylius_ai_translation.ui.current_value'), $locale))
                    ->setPath('.'),
            )
            // `required: false` on the select filters: Symfony hides the placeholder of a required
            // choice once a value is picked, and "All" could never be picked again.
            ->addFilter(
                SelectFilter::create('catalogue', [])
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.catalogue')
                    ->setFormOptions(['choices' => $this->choices->getGroupedCatalogueChoices(), 'required' => false, 'help' => 'cyllene_digital_sylius_ai_translation.ui.catalogue_filter_help']),
            )
            ->addFilter(
                SelectFilter::create('locale', [])
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.locale')
                    ->setFormOptions([
                        'choices' => $localeChoices,
                        'required' => false,
                        'help' => 'cyllene_digital_sylius_ai_translation.ui.locale_help',
                        'placeholder' => $this->translator->trans('cyllene_digital_sylius_ai_translation.ui.locale_default', [
                            'label' => false !== $defaultLabel ? $defaultLabel : $defaultLocale,
                        ]),
                    ]),
            )
            ->addFilter(
                StringFilter::create('search', null, StringFilter::TYPE_CONTAINS)
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.search')
                    ->setTemplate('@CylleneDigitalSyliusAiTranslationPlugin/admin/shared/grid/filter/string_with_help.html.twig')
                    ->addOption('help', 'cyllene_digital_sylius_ai_translation.ui.search_help'),
            )
            ->addFilter(
                BooleanFilter::create('missing')
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.only_missing')
                    ->setFormOptions(['help' => 'cyllene_digital_sylius_ai_translation.ui.only_missing_help']),
            )
            ->addFilter(
                BooleanFilter::create('modified')
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.only_modified')
                    ->setFormOptions(['help' => 'cyllene_digital_sylius_ai_translation.ui.only_modified_help']),
            )
        ;

        // Read-only for who may not edit: no row action (and the cells open no modal).
        if ($this->authorizationChecker->isGranted(Permission::Edit->value)) {
            $gridBuilder->addActionGroup(ItemActionGroup::create(
                Action::create('edit', 'edit_translation')
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.edit')
                    ->setIcon('tabler:pencil'),
            ));
        }
    }
}

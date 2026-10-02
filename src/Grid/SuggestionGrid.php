<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Grid;

use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelScopeResolver;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use Sylius\Bundle\GridBundle\Builder\Action\Action;
use Sylius\Bundle\GridBundle\Builder\Action\DeleteAction;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\BulkActionGroup;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\ItemActionGroup;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Bundle\GridBundle\Builder\Field\TwigField;
use Sylius\Bundle\GridBundle\Builder\Filter\ExistsFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\SelectFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\StringFilter;
use Sylius\Bundle\GridBundle\Grid\AbstractGrid;
use Sylius\Bundle\GridBundle\Grid\ResourceAwareGridInterface;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\Builder\GridBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The pending suggestions of the back-office review screen, as a Sylius grid: native
 * filters, sortable confidence, page-size selector, pagination and bulk selection. A
 * host project extends it like any Sylius grid (add a column, a filter, an action).
 *
 * The grid is scoped by the page-level channel filter (`?scope=`): the data source is
 * the channel's pending suggestions only, resolved at build time from the request, and
 * the locale filter offers that channel's locales. Row and bulk actions post to the
 * plugin's own routes — the row "reject" is Sylius' delete action (confirmation
 * modal included), the suggestion being deleted on rejection.
 */
#[AsGrid(name: self::NAME)]
final class SuggestionGrid extends AbstractGrid implements ResourceAwareGridInterface
{
    public const string NAME = 'cyllene_digital_sylius_ai_translation_admin_suggestion';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ChannelScopeResolver $scopeResolver,
        private readonly GridChoiceProvider $choices,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[\Override]
    public function getResourceClass(): string
    {
        return TranslationSuggestion::class;
    }

    #[\Override]
    public function buildGrid(GridBuilderInterface $gridBuilder): void
    {
        $scope = $this->scopeResolver->fromMainRequest();
        $request = $this->requestStack->getMainRequest();

        $gridBuilder
            ->setRepositoryMethod('createPendingQueryBuilder', [$scope])
            ->orderBy('confidence', 'desc')
            ->setLimits([25, 50, 100])
            // Not a column: the last sort key, which GridViewBuilder adds to every sorting.
            ->addField(StringField::create('id')->setEnabled(false)->setSortable(true))
            ->addField(
                TwigField::create('key', '@CylleneDigitalSyliusAiTranslationPlugin/admin/suggestion/grid/field/key.html.twig')
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.translation_key')
                    ->setPath('.'),
            )
            ->addField(
                TwigField::create('sourceValue', '@CylleneDigitalSyliusAiTranslationPlugin/admin/suggestion/grid/field/source.html.twig')
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.source_text')
                    ->setPath('.'),
            )
            ->addField(
                TwigField::create('suggestedValue', '@CylleneDigitalSyliusAiTranslationPlugin/admin/suggestion/grid/field/suggested.html.twig')
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.suggested_translation')
                    ->setPath('.'),
            )
            ->addField(
                TwigField::create('locale', '@CylleneDigitalSyliusAiTranslationPlugin/admin/suggestion/grid/field/locale.html.twig')
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.locale')
                    ->setOption('vars', ['th_class' => 'text-nowrap']),
            )
            ->addField(
                TwigField::create('confidence', '@CylleneDigitalSyliusAiTranslationPlugin/admin/suggestion/grid/field/confidence.html.twig')
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.confidence')
                    ->setSortable(true)
                    ->setOption('vars', ['th_class' => 'text-nowrap']),
            )
            ->addField(
                TwigField::create('provider', '@CylleneDigitalSyliusAiTranslationPlugin/admin/suggestion/grid/field/provider.html.twig')
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.provider'),
            )
            // `required: false` on the select filters: Symfony hides the placeholder of a required
            // choice once a value is picked, and "All" could never be picked again.
            ->addFilter(
                SelectFilter::create('catalogue', [])
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.catalogue')
                    ->setFormOptions(['choices' => $this->choices->getCatalogueChoices(), 'required' => false, 'help' => 'cyllene_digital_sylius_ai_translation.ui.filter_catalogue_help']),
            )
            ->addFilter(
                SelectFilter::create('locale', [])
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.locale')
                    ->setFormOptions(['choices' => $this->choices->getLocaleChoices($scope, $request?->getLocale()), 'required' => false, 'help' => 'cyllene_digital_sylius_ai_translation.ui.filter_locale_help']),
            )
            ->addFilter(
                // Yes = only the rows whose generation failed (error on the row, no value yet).
                // Sylius offers this filter "No" and "Yes" alone, "No" first: any use of the
                // form would then hide the failed rows, with no way back to all of them.
                ExistsFilter::create('failed', 'generationError')
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.generation_failed')
                    ->setFormOptions(['placeholder' => 'sylius.ui.all', 'help' => 'cyllene_digital_sylius_ai_translation.ui.generation_failed_filter_help']),
            )
            ->addFilter(
                StringFilter::create('search', ['key', 'sourceValue', 'suggestedValue'], StringFilter::TYPE_CONTAINS)
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.search')
                    ->setTemplate('@CylleneDigitalSyliusAiTranslationPlugin/admin/shared/grid/filter/string_with_help.html.twig')
                    ->addOption('help', 'cyllene_digital_sylius_ai_translation.ui.filter_search_help'),
            )
        ;

        // The decisions belong to who may review: without it, the list is read-only.
        if (!$this->authorizationChecker->isGranted(Permission::Review->value)) {
            return;
        }

        $gridBuilder
            ->addActionGroup(ItemActionGroup::create(
                Action::create('approve', 'approve_suggestion')
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.approve')
                    ->setIcon('tabler:check'),
                Action::create('review', 'review_suggestion')
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.review_suggestion')
                    ->setIcon('tabler:pencil-check'),
                // Sylius' own delete action and confirmation modal (the template only
                // resolves the route itself): it posts `_method=DELETE` with a csrf token
                // named after the row id — the reject route honours it.
                DeleteAction::create()
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.reject')
                    ->setTemplate('@CylleneDigitalSyliusAiTranslationPlugin/admin/suggestion/grid/action/reject.html.twig'),
            ))
            ->addActionGroup(BulkActionGroup::create(
                // The scope rides along as a hidden form input: the bulk routes pin the
                // batch to the page it was submitted from (another channel's rows are
                // never reached by a hand-posted ids list).
                Action::create('approve', 'approve_suggestions')
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.approve_selected')
                    ->setOptions(['scope' => $scope]),
                Action::create('reject', 'reject_suggestions')
                    ->setLabel('cyllene_digital_sylius_ai_translation.ui.reject_selected')
                    ->setOptions(['scope' => $scope]),
            ))
        ;
    }
}

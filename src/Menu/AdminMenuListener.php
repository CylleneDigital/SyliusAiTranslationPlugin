<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Menu;

use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Adds the "Translations" entry to the Sylius admin sidebar.
 *
 * The listener runs with a very low priority on purpose: it appends its entry after the host
 * application had a chance to reorder the menu. KnpMenu's `reorderChildren()` throws when the
 * given order does not list every child, so adding our entry earlier would break any host that
 * reorders the root menu with a fixed list. Hosts that reorder at a priority lower than
 * {@see self::PRIORITY} must either list `cyllene_digital_sylius_ai_translation` or reorder tolerantly
 * (see "Menu" in docs/integration/theming.md).
 */
final readonly class AdminMenuListener
{
    public const int PRIORITY = -1024;

    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function __invoke(MenuBuilderEvent $event): void
    {
        // Both screens answer 403 without the read permission: no entry leading there.
        if (!$this->authorizationChecker->isGranted(Permission::View->value)) {
            return;
        }

        $menu = $event->getMenu();

        $translations = $menu
            ->addChild('cyllene_digital_sylius_ai_translation')
            ->setLabel('cyllene_digital_sylius_ai_translation.ui.menu.title')
            ->setLabelAttribute('icon', 'tabler:language');

        $translations
            ->addChild('cyllene_digital_sylius_ai_translation_browser', ['route' => 'cyllene_digital_sylius_ai_translation_admin_translation_index'])
            ->setLabel('cyllene_digital_sylius_ai_translation.ui.menu.translations')
            ->setLabelAttribute('icon', 'tabler:pencil');

        $translations
            ->addChild('cyllene_digital_sylius_ai_translation_suggestions', ['route' => 'cyllene_digital_sylius_ai_translation_admin_suggestion_index'])
            ->setLabel('cyllene_digital_sylius_ai_translation.ui.menu.suggestions')
            ->setLabelAttribute('icon', 'tabler:sparkles');
    }
}

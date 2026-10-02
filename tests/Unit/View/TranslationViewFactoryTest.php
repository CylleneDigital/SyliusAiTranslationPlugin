<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Unit\View;

use CylleneDigital\AiTranslationBundle\Catalogue\TranslationCatalogue;
use CylleneDigital\AiTranslationBundle\Entity\TranslationOverride;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\SyliusAiTranslationPlugin\View\TranslationViewFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TranslationViewFactoryTest extends TestCase
{
    public function testModifiedOnlyReadsTheModifiedCataloguesAlone(): void
    {
        $override = $this->createStub(TranslationOverride::class);
        $override->method('getCatalogue')->willReturn('shop/Product/messages');

        $manager = $this->manager();
        $manager->expects(self::once())->method('getOverridesForLocale')->with('fr_FR', '')->willReturn([$override]);
        $manager->expects(self::once())->method('getTranslationsForCatalogue')->with('shop/Product/messages', 'fr_FR', '')->willReturn([
            'product.name' => ['original' => 'Nom', 'override' => 'Nom du produit', 'hasOverride' => true, 'inherited' => null, 'inheritedFrom' => null],
            'product.price' => ['original' => 'Prix', 'override' => null, 'hasOverride' => false, 'inherited' => null, 'inheritedFrom' => null],
        ]);

        $entries = $this->entries($manager, onlyOverrides: true);

        self::assertSame(['product.name'], array_column($entries, 'key'));
    }

    public function testModifiedOnlyReadsNoFileWhenNothingIsModified(): void
    {
        $manager = $this->manager();
        $manager->expects(self::once())->method('getOverridesForLocale')->willReturn([]);
        $manager->expects(self::never())->method('getTranslationsForCatalogue');
        $manager->expects(self::never())->method('getEffectiveOverridesByCatalogue');

        self::assertSame([], $this->entries($manager, onlyOverrides: true));
    }

    private function manager(): TranslationManagerInterface&MockObject
    {
        $manager = $this->createMock(TranslationManagerInterface::class);
        $manager->method('getCatalogues')->willReturn(array_map(
            TranslationCatalogue::fromIdentifier(...),
            ['admin/Dashboard/messages', 'messages', 'shop/Checkout/messages', 'shop/Product/messages'],
        ));
        $manager->method('getCatalogueIdentifiers')->willReturn(['admin/Dashboard/messages', 'messages', 'shop/Checkout/messages', 'shop/Product/messages']);
        $manager->method('getEffectiveOverridesByCatalogue')->willReturn([]);
        $manager->method('getOriginalValues')->willReturn([]);

        return $manager;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entries(TranslationManagerInterface $manager, bool $onlyOverrides): array
    {
        return (new TranslationViewFactory($manager))->getEntries('fr_FR', '', '', '', '', '', $onlyOverrides, false, 'en_US', 'en_US', ['en_US', 'fr_FR']);
    }
}

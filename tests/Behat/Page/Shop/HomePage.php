<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Page\Shop;

use FriendsOfBehat\PageObjectExtension\Page\SymfonyPage;

/** The shop homepage of the test application, which renders the "app.welcome" key of the host catalogue. */
final class HomePage extends SymfonyPage
{
    public function getRouteName(): string
    {
        return 'sylius_shop_homepage';
    }

    /** The homepage of the channel answering to that hostname: with several channels, the host picks one. */
    public function openOn(string $hostname, string $locale): void
    {
        $this->getDriver()->visit(\sprintf('http://%s/%s/', $hostname, $locale));
    }

    public function welcome(): string
    {
        return $this->getDocument()->find('css', '[data-test-welcome]')?->getText() ?? '';
    }
}

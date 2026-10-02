<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Step\Then;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Page\Shop\HomePage;
use Webmozart\Assert\Assert;

/**
 * What a customer reads in the shop: the end of the chain, past the back office - the
 * translator the storefront uses must serve the override, in the right locale and channel.
 */
final class ReadingTranslationsContext implements Context
{
    public function __construct(
        private readonly HomePage $homePage,
    ) {
    }

    #[Then('customers shopping on :hostname in :shopLocale should be welcomed with :text')]
    public function customersShoppingOnInShouldBeWelcomedWith(string $hostname, string $shopLocale, string $text): void
    {
        $this->homePage->openOn($hostname, $shopLocale);

        Assert::same($this->homePage->welcome(), $text);
    }

    #[Then('customers shopping in :shopLocale should be welcomed with :text')]
    public function customersShoppingInShouldBeWelcomedWith(string $shopLocale, string $text): void
    {
        $this->homePage->open(['_locale' => $shopLocale]);

        Assert::same($this->homePage->welcome(), $text);
    }
}

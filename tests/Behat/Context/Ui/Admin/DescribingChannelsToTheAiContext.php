<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use CylleneDigital\AiTranslationBundle\Scope\ScopeParametersManager;
use Doctrine\ORM\EntityManagerInterface;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Element\Admin\Channel\PromptContextFormElement;
use Webmozart\Assert\Assert;

/**
 * The "AI translation" section of the admin channel form. Opening and saving the form goes
 * through the Sylius steps ("I want to modify a channel ...", "I save my changes").
 */
final class DescribingChannelsToTheAiContext implements Context
{
    public function __construct(
        private readonly PromptContextFormElement $promptContext,
        private readonly ScopeParametersManager $scopeParameters,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Given('the AI is told :context when translating for the :channelCode channel')]
    public function theAiIsToldWhenTranslatingForTheChannel(string $context, string $channelCode): void
    {
        $this->scopeParameters->setPromptContext($channelCode, $context);
    }

    #[When('I describe it to the AI as :context')]
    public function iDescribeItToTheAiAs(string $context): void
    {
        $this->promptContext->describe($context);
    }

    #[When('I remove its description for the AI')]
    public function iRemoveItsDescriptionForTheAi(): void
    {
        $this->promptContext->describe('');
    }

    #[Then('its description for the AI should be :context')]
    public function itsDescriptionForTheAiShouldBe(string $context): void
    {
        Assert::same($this->promptContext->description(), $context);
    }

    #[Then('the AI should be told :context when translating for the :channelCode channel')]
    public function theAiShouldBeToldWhenTranslatingForTheChannel(string $context, string $channelCode): void
    {
        $this->entityManager->clear();

        Assert::same($this->scopeParameters->getPromptContext($channelCode), $context);
    }

    #[Then('the AI should be told nothing more when translating for the :channelCode channel')]
    public function theAiShouldBeToldNothingMoreWhenTranslatingForTheChannel(string $channelCode): void
    {
        $this->entityManager->clear();

        Assert::null($this->scopeParameters->getPromptContext($channelCode));
    }
}

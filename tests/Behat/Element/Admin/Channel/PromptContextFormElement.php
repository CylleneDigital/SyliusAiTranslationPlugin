<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Element\Admin\Channel;

use Behat\Mink\Exception\ElementNotFoundException;
use FriendsOfBehat\PageObjectExtension\Element\Element;

/** The "AI translation" section the plugin adds to the admin channel form. */
final class PromptContextFormElement extends Element
{
    public function describe(string $context): void
    {
        $this->field()->setValue($context);
    }

    public function description(): string
    {
        $value = $this->field()->getValue();

        return \is_string($value) ? $value : '';
    }

    private function field(): \Behat\Mink\Element\NodeElement
    {
        return $this->getDocument()->find('css', 'textarea[name$="[llmPromptContext]"]')
            ?? throw new ElementNotFoundException($this->getSession()->getDriver(), 'textarea', 'name', 'llmPromptContext');
    }
}

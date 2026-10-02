<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Page\Admin;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use FriendsOfBehat\PageObjectExtension\Page\SymfonyPage;

/**
 * What the two screens of the plugin share: grid rows found by their translation key, the
 * flashes, the channel selector, and the wait a Bootstrap modal needs in a real browser.
 */
abstract class AdminScreenPage extends SymfonyPage
{
    public function isForbidden(): bool
    {
        return 403 === $this->getSession()->getStatusCode();
    }

    public function hasRow(string $key): bool
    {
        return null !== $this->findRow($key);
    }

    public function rowText(string $key): string
    {
        return $this->row($key)->getText();
    }

    /** @return list<string> */
    public function flashes(): array
    {
        return array_values(array_map(
            static fn (NodeElement $flash): string => $flash->getText(),
            $this->getDocument()->findAll('css', '[data-test-sylius-flash-message]'),
        ));
    }

    /** Whether a flash says so - awaited: in a real browser, the page a click leads to is not there yet. */
    public function isTold(string $message): bool
    {
        return (bool) $this->getDocument()->waitFor(5, fn (): bool => str_contains(implode(' | ', $this->flashes()), $message));
    }

    public function shows(string $selector): bool
    {
        return null !== $this->getDocument()->find('css', $selector);
    }

    public function chooseChannel(string $code): void
    {
        $this->getDocument()->selectFieldOption('scope_selector', $code);
        $this->getDocument()->waitFor(5, fn (): bool => str_contains($this->getSession()->getCurrentUrl(), 'scope=' . $code));
    }

    /** Whether Sylius' filter form carries the page's channel along. */
    public function filtersCarryChannel(string $code): bool
    {
        return $this->getDocument()->waitFor(5, fn (): bool => null !== $this->getDocument()->find('css', \sprintf('form input[type="hidden"][name="scope"][value="%s"]', $code)));
    }

    protected function row(string $key): NodeElement
    {
        return $this->findRow($key) ?? throw new ElementNotFoundException($this->getSession()->getDriver(), 'grid row', 'key', $key);
    }

    protected function findRow(string $key): ?NodeElement
    {
        return $this->getDocument()->find('xpath', \sprintf('//tr[.//code[normalize-space()="%s"]]', $key));
    }

    protected function waitForModal(string $id): NodeElement
    {
        $this->getDocument()->waitFor(5, fn (): bool => null !== $this->getDocument()->find('css', \sprintf('#%s.show', $id)));

        return $this->getDocument()->find('css', \sprintf('#%s.show', $id))
            ?? throw new ElementNotFoundException($this->getSession()->getDriver(), 'open modal', 'id', $id);
    }

    protected function press(string $selector, ?NodeElement $within = null): void
    {
        $button = ($within ?? $this->getDocument())->find('css', $selector)
            ?? throw new ElementNotFoundException($this->getSession()->getDriver(), 'button', 'css', $selector);

        $button->press();
    }
}

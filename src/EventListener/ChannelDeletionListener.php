<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\EventListener;

use CylleneDigital\AiTranslationBundle\Scope\ScopeParametersManager;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Channel\Model\ChannelInterface;

/**
 * A deleted channel takes its per-scope settings (prompt context) with it — staged on
 * the pre-delete event, flushed with the deletion itself.
 */
final readonly class ChannelDeletionListener
{
    public function __construct(
        private ScopeParametersManager $scopeParameters,
    ) {
    }

    public function __invoke(ResourceControllerEvent $event): void
    {
        $channel = $event->getSubject();

        if (!$channel instanceof ChannelInterface || null === $channel->getCode()) {
            return;
        }

        $this->scopeParameters->remove($channel->getCode(), flush: false);
    }
}

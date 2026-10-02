<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Form\Extension;

use CylleneDigital\AiTranslationBundle\Scope\ScopeParametersManager;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelType;
use Sylius\Component\Channel\Model\ChannelInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Adds the AI prompt context to the channel form. The value is NOT a channel property:
 * it lives in the engine's per-scope settings (scope = the channel code), so the host's
 * Channel entity is left untouched. The field is unmapped, filled from those settings
 * when the form is built, and written back after a valid submit — staged without flush,
 * inside the resource controller's own flush (an invalid form never leaves a row behind).
 */
final class ChannelTypeExtension extends AbstractTypeExtension
{
    public const string FIELD = 'llmPromptContext';

    public function __construct(
        private readonly ScopeParametersManager $scopeParameters,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(self::FIELD, TextareaType::class, [
            'label' => 'cyllene_digital_sylius_ai_translation.form.channel.llm_prompt_context',
            'help' => 'cyllene_digital_sylius_ai_translation.form.channel.llm_prompt_context_help',
            'required' => false,
            'mapped' => false,
            'attr' => [
                'rows' => 4,
                'placeholder' => 'cyllene_digital_sylius_ai_translation.form.channel.llm_prompt_context_placeholder',
            ],
        ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            $channel = $event->getData();
            $code = $channel instanceof ChannelInterface ? $channel->getCode() : null;

            if (null !== $code && '' !== $code) {
                $event->getForm()->get(self::FIELD)->setData($this->scopeParameters->getPromptContext($code));
            }
        });

        // After the validation listener (priority 0): only a valid channel writes its context.
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $channel = $event->getData();

            if (!$form->isValid() || !$channel instanceof ChannelInterface) {
                return;
            }

            $code = $channel->getCode();

            if (null === $code || '' === $code) {
                return;
            }

            $context = $form->get(self::FIELD)->getData();
            $this->scopeParameters->setPromptContext($code, \is_string($context) ? $context : null, flush: false);
        }, -10);
    }

    #[\Override]
    public static function getExtendedTypes(): iterable
    {
        return [ChannelType::class];
    }
}

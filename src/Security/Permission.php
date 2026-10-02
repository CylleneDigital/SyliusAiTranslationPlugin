<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Security;

/**
 * What an admin may do on the two screens, one permission per kind of consequence: reading,
 * changing a wording by hand, writing a whole file of them, deciding on AI suggestions, and
 * spending the provider budget. Each is granted by its own role, all of them by
 * {@see self::MANAGE_ROLE} — see {@see PermissionVoter}.
 *
 * The values are the attributes checked by `#[IsGranted]` and `is_granted()`.
 */
enum Permission: string
{
    /** Holds every permission. */
    public const string MANAGE_ROLE = 'ROLE_AI_TRANSLATION_MANAGE';

    /** Both screens, the coverage, the view export. Held by whoever holds any other permission. */
    case View = 'cyllene_digital_sylius_ai_translation.view';

    /** Saving or removing one override, purging the orphan ones. */
    case Edit = 'cyllene_digital_sylius_ai_translation.edit';

    /** Importing a file of overrides. */
    case Import = 'cyllene_digital_sylius_ai_translation.import';

    /** Approving or rejecting suggestions. */
    case Review = 'cyllene_digital_sylius_ai_translation.review';

    /** Estimating and launching generations: billed calls to the AI provider. */
    case Generate = 'cyllene_digital_sylius_ai_translation.generate';

    public function role(): string
    {
        return match ($this) {
            self::View => 'ROLE_AI_TRANSLATION_VIEW',
            self::Edit => 'ROLE_AI_TRANSLATION_EDIT',
            self::Import => 'ROLE_AI_TRANSLATION_IMPORT',
            self::Review => 'ROLE_AI_TRANSLATION_REVIEW',
            self::Generate => 'ROLE_AI_TRANSLATION_GENERATE',
        };
    }
}

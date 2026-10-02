<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Input;

/**
 * The row the edit modal posts: which entry (key, catalogue, locale, channel) and, for a
 * save, its value. Raw input: the controller checks the locale and the channel against
 * what the host exposes.
 */
final readonly class OverrideRow
{
    public string $key;

    public string $catalogue;

    public string $locale;

    public function __construct(
        string $key = '',
        string $catalogue = '',
        string $locale = '',
        /** The posted channel code, '' = global — not yet checked against the known channels. */
        public string $scope = '',
        /** Kept as typed, its edge spaces included: a label concatenated in a template may need them. */
        public string $value = '',
    ) {
        $this->key = trim($key);
        $this->catalogue = trim($catalogue);
        $this->locale = trim($locale);
    }
}

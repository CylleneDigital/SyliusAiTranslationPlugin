<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Input;

use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * The coordinates of a generation run, as the generation form submits them — to the
 * estimate (query string) and to the run itself (form post), which must read the very
 * same fields. Raw input: the controller checks each coordinate against what the host
 * and the selected channel expose before anything is estimated or billed.
 */
final readonly class GenerationRun
{
    public function __construct(
        #[SerializedName('source_locale')]
        public string $sourceLocale = '',
        #[SerializedName('target_locale')]
        public string $targetLocale = '',
        /** '' = every catalogue, one run each. */
        public string $catalogue = '',
        /** '' = the configured default provider. */
        public string $provider = '',
        /** Every key, not only the missing ones. */
        public bool $all = false,
        #[SerializedName('retry_errors')]
        public bool $retryErrors = false,
        /** The posted channel code, '' = global — not yet checked against the known channels. */
        public string $scope = '',
    ) {
    }

    public function catalogueOrNull(): ?string
    {
        return '' !== $this->catalogue ? $this->catalogue : null;
    }

    public function providerOrNull(): ?string
    {
        return '' !== $this->provider ? $this->provider : null;
    }
}

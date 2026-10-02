<?php

declare(strict_types=1);

use Behat\Config\Config;

// One suite per feature, selected by its tag; the Sylius contexts are services of the test kernel.
// Every suite takes the same contexts: a scenario edits in the back office and reads the result
// in the shop, whatever its feature is about.
$contexts = [
    'sylius.behat.context.hook.doctrine_orm',
    'sylius.behat.context.hook.session',

    'sylius.behat.context.transform.shared_storage',
    'sylius.behat.context.transform.locale',
    'sylius.behat.context.transform.channel',

    'sylius.behat.context.setup.channel',
    'sylius.behat.context.setup.locale',
    'cyllene_digital_sylius_ai_translation.behat.context.setup.translation',

    'cyllene_digital_sylius_ai_translation.behat.context.ui.admin.managing_translations',
    'cyllene_digital_sylius_ai_translation.behat.context.ui.admin.reviewing_suggestions',
    'cyllene_digital_sylius_ai_translation.behat.context.ui.admin.describing_channels_to_the_ai',
    'cyllene_digital_sylius_ai_translation.behat.context.ui.shop.reading_translations',
    'sylius.behat.context.ui.admin.managing_channels',
    'sylius.behat.context.ui.save',
];

$features = [
    'browsing_translations',
    'describing_a_channel_to_the_ai',
    'editing_a_translation',
    'generating_suggestions',
    'importing_overrides',
    'purging_orphan_overrides',
    'restricting_access',
    'reviewing_suggestions',
    'reading_overridden_translations',
    'reading_translations_per_channel',
];

$suites = [];
foreach ($features as $feature) {
    $suites['ui_' . $feature] = [
        'contexts' => $contexts,
        'filters' => [
            'tags' => '@' . $feature . '&&@ui',
        ],
    ];
}

return new Config([
    'default' => [
        'suites' => $suites,
    ],
]);

<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Override;

/** What a hand edit of one entry came to — see {@see ManualOverrideEditor::edit()}. */
enum ManualEditOutcome
{
    /** The value is stored as the entry's override. */
    case Saved;

    /** The value is the one the entry shows without its override: the override is removed instead. */
    case Reverted;

    /** An emptied entry with nothing to fall back on: its override is removed. */
    case Cleared;

    /** The entry already shows this value: nothing written. */
    case Unchanged;

    /** An empty value for an entry that has neither a value nor an override: nothing to do. */
    case EmptyIgnored;

    /** An empty value for an entry that has one to show: refused, the reset is how its override goes. */
    case EmptyRefused;

    /** The catalogue has no file left and the entry no override: a new orphan is refused. */
    case CatalogueUnknown;

    /** The value does not match the catalogue's declared syntax (ICU or legacy). */
    case SyntaxInvalid;

    /** The key is longer than the override table stores. */
    case KeyTooLong;

    /** The engine refused the coordinates: a locale it would never serve (`fr-FR` for `fr_FR`), a catalogue identifier too long. */
    case Invalid;
}

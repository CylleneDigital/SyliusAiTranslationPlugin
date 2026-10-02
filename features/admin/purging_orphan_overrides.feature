@purging_orphan_overrides
Feature: Purging the orphan overrides
    In order not to keep overrides of translation files that no longer exist
    As an Administrator
    I want to be warned about them and to delete them in one go

    Background:
        Given the store operates on a single channel in "United States"
        And the store has locale "French (France)"
        And the "old.key" key of the "gone/Old/messages" catalogue is overridden with "Orphan" in "fr_FR"

    @ui
    Scenario: Purging the overrides whose catalogue is gone
        Given I am logged in as an administrator managing the translations
        And the "app.dashboard" key of the "messages" catalogue is overridden with "Accueil" in "fr_FR"
        When I browse the translations
        Then I should be warned about 1 orphan override
        When I purge the orphan overrides
        Then I should be told "1 orphan override deleted"
        And the "old.key" key of the "gone/Old/messages" catalogue should not be overridden in "fr_FR"
        But the "app.dashboard" key of the "messages" catalogue should be overridden with "Accueil" in "fr_FR"

    @ui @javascript
    Scenario: The purge asks for a confirmation, and does nothing when it is declined
        Given I am logged in as an administrator managing the translations
        When I browse the translations
        And I purge the orphan overrides but change my mind
        Then the "old.key" key of the "gone/Old/messages" catalogue should be overridden with "Orphan" in "fr_FR"

    @ui @javascript
    Scenario: Confirming the purge deletes the orphan overrides
        Given I am logged in as an administrator managing the translations
        When I browse the translations
        And I purge the orphan overrides and confirm
        Then I should be told "1 orphan override deleted"
        And the "old.key" key of the "gone/Old/messages" catalogue should not be overridden in "fr_FR"

    @ui
    Scenario: A reader is warned but not offered to purge
        Given I am logged in as an administrator who may only "view" the translations
        When I browse the translations
        Then I should be warned about 1 orphan override
        But I should not be offered to purge them

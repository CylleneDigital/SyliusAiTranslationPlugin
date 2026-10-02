@editing_a_translation
Feature: Editing a translation by hand
    In order to fix a wording without a deployment
    As an Administrator
    I want to override the value of a translation key, and to take the override back

    Background:
        Given the store operates on a single channel in "United States"
        And the store has locale "French (France)"
        And I am logged in as an administrator managing the translations

    @ui @javascript
    Scenario: Translating a key that had no translation
        When I browse the translations in "fr_FR"
        And I edit the "app.welcome" key
        Then the edit modal should be about the "app.welcome" key
        And I should not be offered to restore its original value
        When I set its value to "Bienvenue dans la boutique"
        Then I should be told "Translation saved"
        And the "app.welcome" key of the "messages" catalogue should be overridden with "Bienvenue dans la boutique" in "fr_FR"

    @ui @javascript
    Scenario: Saving the value the key already shows writes nothing
        When I browse the translations in "fr_FR"
        And I edit the "app.dashboard" key
        And I set its value to "Tableau de bord"
        Then the "app.dashboard" key of the "messages" catalogue should not be overridden in "fr_FR"

    @ui @javascript
    Scenario: Restoring the original value removes the override
        Given the "app.dashboard" key of the "messages" catalogue is overridden with "Accueil" in "fr_FR"
        When I browse the translations in "fr_FR"
        And I edit the "app.dashboard" key
        And I restore its original value
        Then the "app.dashboard" key of the "messages" catalogue should not be overridden in "fr_FR"
        And the "app.dashboard" key should show "Tableau de bord"

@browsing_translations
Feature: Browsing the translations of the project
    In order to know what the shop says in each language
    As an Administrator
    I want to browse the translation catalogues, their gaps included

    Background:
        Given the store operates on a single channel in "United States"
        And the store has locale "French (France)"
        And I am logged in as an administrator managing the translations

    @ui
    Scenario: Listing the keys of the default locale
        When I browse the translations
        Then I should see the "app.welcome" key
        And the "app.welcome" key should show "Welcome to the shop"

    @ui
    Scenario: A key only written in another language is listed as untranslated
        When I browse the translations in "fr_FR"
        Then the "app.dashboard" key should show "Tableau de bord"
        And the "app.welcome" key should be untranslated
        And the "app.welcome" key should show "Welcome to the shop"

    @ui
    Scenario: Keeping the untranslated keys only
        When I browse the untranslated keys in "fr_FR"
        Then I should see the "app.welcome" key
        But I should not see the "app.dashboard" key

    @ui
    Scenario: An override shows instead of the file value
        Given the "app.dashboard" key of the "messages" catalogue is overridden with "Accueil" in "fr_FR"
        When I browse the translations in "fr_FR"
        Then the "app.dashboard" key should show "Accueil"

    @ui @javascript
    Scenario: Choosing a channel reloads the page on it and keeps it through the filters
        When I browse the translations
        And I choose the "WEB-US" channel
        Then the filters should keep the "WEB-US" channel

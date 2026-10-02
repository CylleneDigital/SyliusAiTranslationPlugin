@generating_suggestions
Feature: Generating AI suggestions
    In order to fill the gaps of a language without typing every text
    As an Administrator
    I want to ask the AI for suggestions, knowing what it costs and how it went

    Background:
        Given the store operates on a single channel in "United States"
        And the store has locale "French (France)"
        And I am logged in as an administrator managing the translations

    @ui
    Scenario: Asking for the suggestions of one catalogue
        When I browse the AI suggestions
        And I ask for a generation of the "messages" catalogue into "fr_FR"
        Then I should be told "Generation queued (1 catalogue)"

    @ui @javascript
    Scenario: A generation on every catalogue asks for a confirmation
        When I browse the AI suggestions
        And I ask for a generation of every catalogue into "fr_FR"
        Then I should be asked to confirm a generation on every catalogue
        When I confirm it
        Then I should be told "Generation queued"

    @ui @javascript
    Scenario: The estimate belongs to the coordinates it was asked for
        When I browse the AI suggestions
        And I ask what generating the "messages" catalogue into "fr_FR" would cost
        Then I should be given an estimate of the keys to translate
        When I switch to the "deepl" provider
        Then the estimate should be cleared

    @ui
    Scenario: The last runs are listed, failures included
        Given a generation of the "messages" catalogue into "fr_FR" produced 3 suggestions
        And a generation of the "shop/Product/messages" catalogue into "fr_FR" failed with "Provider answered 401"
        When I browse the AI suggestions
        Then the journal should start with the failure "Provider answered 401"
        And the journal should list a run of 3 suggestions

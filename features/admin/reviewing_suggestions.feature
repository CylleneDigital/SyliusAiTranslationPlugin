@reviewing_suggestions
Feature: Reviewing the AI suggestions
    In order to keep a human in charge of what the shop says
    As an Administrator
    I want to approve, correct or reject what the AI proposed

    Background:
        Given the store operates on a single channel in "United States"
        And the store has locale "French (France)"
        And the AI suggested "Bienvenue" for the "app.welcome" key of the "messages" catalogue in "fr_FR"
        And I am logged in as an administrator managing the translations

    @ui
    Scenario: A suggestion is not applied until it is approved
        When I browse the AI suggestions
        Then I should see a suggestion for the "app.welcome" key
        And the "app.welcome" key of the "messages" catalogue should not be overridden in "fr_FR"

    @ui
    Scenario: Approving a suggestion writes the override
        When I browse the AI suggestions
        And I approve the suggestion for the "app.welcome" key
        Then the "app.welcome" key of the "messages" catalogue should now read "Bienvenue" in "fr_FR"
        And there should be no suggestion left for the "app.welcome" key

    @ui
    Scenario: Rejecting a suggestion applies nothing
        When I browse the AI suggestions
        And I reject the suggestion for the "app.welcome" key
        Then there should be no suggestion left for the "app.welcome" key
        And the "app.welcome" key of the "messages" catalogue should not be overridden in "fr_FR"

    @ui @javascript
    Scenario: Correcting a suggestion before approving it
        When I browse the AI suggestions
        And I review the suggestion for the "app.welcome" key
        And I approve it as "Bienvenue chez nous"
        Then the "app.welcome" key of the "messages" catalogue should now read "Bienvenue chez nous" in "fr_FR"

    @ui @javascript
    Scenario: A suggestion cannot be approved with an empty value
        When I browse the AI suggestions
        And I review the suggestion for the "app.welcome" key
        And I empty its value
        Then I should not be able to approve it

    @ui @javascript
    Scenario: Approving several suggestions at once
        Given the AI suggested "Rupture de stock" for the "product.out_of_stock" key of the "shop/Product/messages" catalogue in "fr_FR"
        When I browse the AI suggestions
        And I approve every suggestion at once
        Then the "app.welcome" key of the "messages" catalogue should now read "Bienvenue" in "fr_FR"
        And the "product.out_of_stock" key of the "shop/Product/messages" catalogue should now read "Rupture de stock" in "fr_FR"

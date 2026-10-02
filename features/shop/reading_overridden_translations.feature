@reading_overridden_translations
Feature: Reading the overridden translations in the shop
    In order for the work done in the back office to reach the customers
    As a Customer
    I want the shop to show the wording that was written or approved, and no other

    Background:
        Given the store operates on a single channel in "United States"
        And that channel allows to shop using "English (United States)" and "French (France)" locales

    @ui
    Scenario: A text without a translation falls back on the default language
        Then customers shopping in "en_US" should be welcomed with "Welcome to the shop"
        And customers shopping in "fr_FR" should be welcomed with "Welcome to the shop"

    @ui
    Scenario: An override shows in its language only
        Given the "app.welcome" key of the "messages" catalogue is overridden with "Bienvenue chez nous" in "fr_FR"
        Then customers shopping in "fr_FR" should be welcomed with "Bienvenue chez nous"
        But customers shopping in "en_US" should be welcomed with "Welcome to the shop"

    @ui
    Scenario: An override written for a channel shows in that channel
        Given the "app.welcome" key of the "messages" catalogue is overridden with "Bienvenue aux États-Unis" in "fr_FR" for the "WEB-US" channel
        Then customers shopping in "fr_FR" should be welcomed with "Bienvenue aux États-Unis"

    @ui
    Scenario: A suggestion does not reach the shop before it is approved
        Given the AI suggested "Bienvenue" for the "app.welcome" key of the "messages" catalogue in "fr_FR"
        Then customers shopping in "fr_FR" should be welcomed with "Welcome to the shop"

    @ui
    Scenario: An approved suggestion reaches the shop
        Given the AI suggested "Bienvenue" for the "app.welcome" key of the "messages" catalogue in "fr_FR"
        And I am logged in as an administrator managing the translations
        When I browse the AI suggestions
        And I approve the suggestion for the "app.welcome" key
        Then customers shopping in "fr_FR" should be welcomed with "Bienvenue"

    @ui
    Scenario: A rejected suggestion never reaches the shop
        Given the AI suggested "Bienvenue" for the "app.welcome" key of the "messages" catalogue in "fr_FR"
        And I am logged in as an administrator managing the translations
        When I browse the AI suggestions
        And I reject the suggestion for the "app.welcome" key
        Then customers shopping in "fr_FR" should be welcomed with "Welcome to the shop"

    @ui
    Scenario: An imported override reaches the shop once the import is confirmed
        Given I am logged in as an administrator managing the translations
        When I browse the translations
        And I import the "overrides.csv" file
        Then customers shopping in "fr_FR" should be welcomed with "Welcome to the shop"
        When I browse the translations
        And I confirm the import
        Then customers shopping in "fr_FR" should be welcomed with "Bienvenue dans la boutique"

    @ui @javascript
    Scenario: A text written by hand in the back office reaches the shop
        Given I am logged in as an administrator managing the translations
        When I browse the translations in "fr_FR"
        And I edit the "app.welcome" key
        And I set its value to "Bienvenue dans la boutique"
        Then I should be told "Translation saved"
        And customers shopping in "fr_FR" should be welcomed with "Bienvenue dans la boutique"

    @ui @javascript
    Scenario: Restoring the original value brings the text of the files back to the shop
        Given the "app.welcome" key of the "messages" catalogue is overridden with "Hello there" in "en_US"
        And I am logged in as an administrator managing the translations
        Then customers shopping in "en_US" should be welcomed with "Hello there"
        When I browse the translations
        And I edit the "app.welcome" key
        And I restore its original value
        Then I should be told "Override deleted"
        And customers shopping in "en_US" should be welcomed with "Welcome to the shop"

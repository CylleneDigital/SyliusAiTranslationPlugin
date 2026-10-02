@reading_translations_per_channel
Feature: Reading the translations of each channel in the shop
    In order to speak differently to the customers of each channel
    As a Customer
    I want to read the wording written for the channel I shop on, and the common one otherwise

    Background:
        Given the store operates on a channel named "Web" with hostname "web.test"
        And that channel allows to shop using "English (United States)" and "French (France)" locales
        And the store also operates on another channel named "B2B" with hostname "b2b.test"
        And that channel allows to shop using "English (United States)" and "French (France)" locales

    @ui
    Scenario: An override written for a channel does not show in another
        Given the "app.welcome" key of the "messages" catalogue is overridden with "Bienvenue, professionnels" in "fr_FR" for the "b2b" channel
        Then customers shopping on "b2b.test" in "fr_FR" should be welcomed with "Bienvenue, professionnels"
        But customers shopping on "web.test" in "fr_FR" should be welcomed with "Welcome to the shop"

    @ui
    Scenario: An override written for every channel shows wherever no channel has its own
        Given the "app.welcome" key of the "messages" catalogue is overridden with "Bienvenue" in "fr_FR"
        And the "app.welcome" key of the "messages" catalogue is overridden with "Bienvenue, professionnels" in "fr_FR" for the "b2b" channel
        Then customers shopping on "web.test" in "fr_FR" should be welcomed with "Bienvenue"
        And customers shopping on "b2b.test" in "fr_FR" should be welcomed with "Bienvenue, professionnels"

    @ui
    Scenario: A suggestion approved for a channel only reaches that channel
        Given the AI suggested "Bienvenue, professionnels" for the "app.welcome" key of the "messages" catalogue in "fr_FR" for the "b2b" channel
        And I am logged in as an administrator managing the translations
        When I browse the AI suggestions of the "b2b" channel
        And I approve the suggestion for the "app.welcome" key
        Then customers shopping on "b2b.test" in "fr_FR" should be welcomed with "Bienvenue, professionnels"
        But customers shopping on "web.test" in "fr_FR" should be welcomed with "Welcome to the shop"

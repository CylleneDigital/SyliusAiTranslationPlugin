@restricting_access
Feature: Restricting what an administrator may do with the translations
    In order to let someone review without spending the AI budget, or read without writing
    As a Store Owner
    I want each kind of action to have its own role

    Background:
        Given the store operates on a single channel in "United States"
        And the store has locale "French (France)"
        And the AI suggested "Bienvenue" for the "app.welcome" key of the "messages" catalogue in "fr_FR"

    @ui
    Scenario: Without any role, neither screen opens
        Given I am logged in as an administrator without any role on the translations
        When I try to browse the translations
        Then I should not be allowed to see them
        When I try to browse the AI suggestions
        Then I should not be allowed to see the suggestions

    @ui
    Scenario: A reader browses the translations without being offered to change them
        Given I am logged in as an administrator who may only "view" the translations
        When I browse the translations in "fr_FR"
        Then I should be able to read them but not to edit them

    @ui
    Scenario: A reader sees the suggestions without their decisions
        Given I am logged in as an administrator who may only "view" the translations
        When I browse the AI suggestions
        Then I should be able to read them but not to decide on them

    @ui
    Scenario: A reviewer decides on the suggestions without generating more
        Given I am logged in as an administrator who may only "review" the translations
        When I browse the AI suggestions
        Then I should be able to decide on them but not to generate more

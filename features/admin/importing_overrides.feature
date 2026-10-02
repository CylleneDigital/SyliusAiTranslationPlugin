@importing_overrides
Feature: Importing a file of overrides
    In order to apply the work of a translator in one go
    As an Administrator
    I want to import a file, see what it would write, then confirm

    Background:
        Given the store operates on a single channel in "United States"
        And the store has locale "French (France)"
        And I am logged in as an administrator managing the translations

    @ui
    Scenario: Nothing is written before the import is confirmed
        When I browse the translations
        And I import the "overrides.csv" file
        Then I should be shown what the import would write
        And the "app.welcome" key of the "messages" catalogue should not be overridden in "fr_FR"

    @ui
    Scenario: Confirming the import writes the overrides
        When I browse the translations
        And I import the "overrides.csv" file
        And I confirm the import
        Then the "app.welcome" key of the "messages" catalogue should be overridden with "Bienvenue dans la boutique" in "fr_FR"

    @ui
    Scenario: Cancelling the import writes nothing
        When I browse the translations
        And I import the "overrides.csv" file
        And I cancel the import
        Then I should be told "Import cancelled"
        And the "app.welcome" key of the "messages" catalogue should not be overridden in "fr_FR"

    @ui
    Scenario: A file that is not a translation file is refused
        When I browse the translations
        And I import the "notes.txt" file
        Then I should be told "File rejected"

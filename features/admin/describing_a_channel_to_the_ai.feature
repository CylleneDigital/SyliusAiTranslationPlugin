@describing_a_channel_to_the_ai
Feature: Describing a channel to the AI
    In order to get suggestions that fit the audience of each channel
    As an Administrator
    I want to give the AI a context of its own for a channel

    Background:
        Given the store operates on a single channel in "United States"
        And I am logged in as an administrator managing the translations

    @ui
    Scenario: Giving a channel its context
        When I want to modify a channel "United States"
        And I describe it to the AI as "Wholesale buyers, terse wording."
        And I save my changes
        Then the AI should be told "Wholesale buyers, terse wording." when translating for the "WEB-US" channel

    @ui
    Scenario: The context of a channel is shown when the channel is edited again
        Given the AI is told "Formal tone, no exclamation mark." when translating for the "WEB-US" channel
        When I want to modify a channel "United States"
        Then its description for the AI should be "Formal tone, no exclamation mark."

    @ui
    Scenario: Emptying the context removes it
        Given the AI is told "Formal tone, no exclamation mark." when translating for the "WEB-US" channel
        When I want to modify a channel "United States"
        And I remove its description for the AI
        And I save my changes
        Then the AI should be told nothing more when translating for the "WEB-US" channel

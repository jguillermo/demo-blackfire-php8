Feature: Heartcheck
  In order to validate api its alive
  As a non authenticated user
  I need to be able to hit endpoint

  Scenario: Get heartbeep
    Given I make a GET request to "healthcheck"
    Then I get a SUCCESSFUL response
    And I validate is HTML response
    And I validate the response is
    """
    ok
    """


  @blackfire
  Scenario: Get heartbeep
    Given I make a GET request to "healthcheck"
    Then I get a SUCCESSFUL response
    And I validate is HTML response
    And I validate the response is
    """
    ok
    """
    And I validate response wall time was below 10 ms
    And I validate response cpu time was below 10 ms
    And I validate response io time was below 10 ms
    And I validate response memory was below 1 MBs
    And I validate response network traffic size was below 1 MBs
@mod @mod_escape
Feature: A teacher can set a time limit for a escape
  In order to restrict the time students have to complete a escape
  As a teacher
  I need to set a time limit

  @javascript
  Scenario: Accessing as student to a escape with time limit
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Escape" to section "1" and I fill the form with:
      | Name | Test escape |
      | Description | Test escape description |
      | timelimit[enabled] | 1 |
      | timelimit[timeunit] | 60 |
      | timelimit[number]   | 1  |
    And I follow "Test escape"
    And I follow "Add a content page"
    And I set the following fields to these values:
      | Page title | Escape page name |
      | Page contents | Single escape page contents |
      | Description | Single button |
    And I press "Save page"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "Test escape"
    Then I should see "You have 1 min to finish the escape."
    And I wait "3" seconds
    And I should see "Time remaining"
    And I press "Single button"
    And I should see "0:00:"
    And I should see "Warning: You have 1 minute or less to finish the escape."
    And I wait "60" seconds
    And I press "Single button"
    And I should see "You ran out of time for this escape."
    And I should see "Your last answer may not have counted if it was answered after the time was up."
    And I should see "Congratulations - end of escape reached"
    And I should not see "Single escape page contents"

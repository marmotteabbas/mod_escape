@mod @mod_escape
Feature: A teacher can password protect a escape
  In order to avoid undesired accesses to escape activities
  As a teacher
  I need to set a password to access the escape

  Scenario: Accessing as student to a protected escape
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
      | Password protected escape | Yes |
      | id_password | moodle_rules |
    And I follow "Test escape"
    And I follow "Add a content page"
    And I set the following fields to these values:
      | Page title | First page name |
      | Page contents | First page contents |
      | Description | The first one |
    And I press "Save page"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "Test escape"
    Then I should see "Test escape is a password protected escape"
    And I should not see "First page contents"
    And I set the field "userpassword" to "moodle"
    And I press "Continue"
    And I should see "Login failed, please try again..."
    And I should see "Test escape is a password protected escape"
    And I set the field "userpassword" to "moodle_rules"
    And I press "Continue"
    And I should see "First page contents"
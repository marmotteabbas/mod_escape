@mod @mod_escape
Feature: link to gradebook on the end of escape page
  In order to allow students to see their escape grades
  As a teacher
  I need to provide a link to gradebook on the end of escape page

  Background:
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
    And I follow "Test escape"
    And I follow "Add a content page"
    And I set the following fields to these values:
      | Page title | First page name |
      | Page contents | First page contents |
      | id_answer_editor_0 | Next page |
      | id_jumpto_0 | Next page |
    And I press "Save page"
    And I select "Add a content page" from the "qtype" singleselect
    And I set the following fields to these values:
      | Page title | Second page name |
      | Page contents | Second page contents |
      | id_answer_editor_0 | Previous page |
      | id_jumpto_0 | Previous page |
      | id_answer_editor_1 | Next page |
      | id_jumpto_1 | Next page |
    And I press "Save page"

  Scenario: Link to gradebook for non practice escape
    Given I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape"
    And I press "Next page"
    And I press "Next page"
    Then I should see "Congratulations - end of escape reached"
    And I should see "View grades"
    And I follow "View grades"
    And I should see "User report - Student 1"
    And I should see "Test escape"

  Scenario: No link to gradebook for non graded escape
    Given I follow "Test escape"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
        | Type | None |
    And I press "Save and display"
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape"
    And I press "Next page"
    And I press "Next page"
    Then I should see "Congratulations - end of escape reached"
    And I should not see "View grades"

  Scenario: No link to gradebook for practice escape
    Given I follow "Test escape"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
        | Practice escape | Yes |
    And I press "Save and display"
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape"
    And I press "Next page"
    And I press "Next page"
    Then I should see "Congratulations - end of escape reached"
    And I should not see "View grades"

  Scenario: No link if Show gradebook to student disabled
    Given I am on "Course 1" course homepage
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | Show gradebook to students | No |
    And I press "Save and display"
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape"
    And I press "Next page"
    And I press "Next page"
    Then I should see "Congratulations - end of escape reached"
    And I should not see "View grades"

  Scenario: No link to gradebook if no gradereport/user:view capability
    Given I log out
    And I log in as "admin"
    And I set the following system permissions of "Student" role:
      | capability | permission |
      | gradereport/user:view | Prevent |
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape"
    And I press "Next page"
    And I press "Next page"
    Then I should see "Congratulations - end of escape reached"
    And I should not see "View grades"

@mod @mod_escape @javascript
Feature: Escape user override
  In order to grant a student special access to a escape
  As a teacher
  I need to create an override for that user.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Tina | Teacher1 | teacher1@example.com |
      | student1 | Sam1 | Student1 | student1@example.com |
      | student2 | Sam2 | Student2 | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group 1 | C1     | G1       |
      | Group 2 | C1     | G2       |
    And the following "activities" exist:
      | activity | name             | intro                   | course | idnumber |
      | escape   | Test escape name | Test escape description | C1     | escape1  |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I follow "Test escape name"
    And I follow "Add a question page"
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title           | True/false question 1 |
      | Page contents        | Cat is an amphibian |
      | id_answer_editor_0   | False |
      | id_response_editor_0 | Correct |
      | id_jumpto_0          | Next page |
      | id_answer_editor_1   | True |
      | id_response_editor_1 | Wrong |
      | id_jumpto_1          | This page |
    And I press "Save page"

  @javascript
  Scenario: Add, modify then delete a user override
    When I follow "Test escape name"
    And I navigate to "User overrides" in current page administration
    And I press "Add user override"
    And I set the following fields to these values:
      | Override user       | Student1 |
      | id_deadline_enabled | 1 |
      | deadline[day]       | 1 |
      | deadline[month]     | January |
      | deadline[year]      | 2020 |
      | deadline[hour]      | 08 |
      | deadline[minute]    | 00 |
    And I press "Save"
    And I should see "Wednesday, 1 January 2020, 8:00"
    Then I click on "Edit" "link" in the "Sam1 Student1" "table_row"
    And I set the following fields to these values:
      | deadline[year] | 2030 |
    And I press "Save"
    And I should see "Tuesday, 1 January 2030, 8:00"
    And I click on "Delete" "link"
    And I press "Continue"
    And I should not see "Sam1 Student1"

  Scenario: Duplicate a user override
    When I follow "Test escape name"
    And I navigate to "User overrides" in current page administration
    And I press "Add user override"
    And I set the following fields to these values:
      | Override user       | Student1 |
      | id_deadline_enabled | 1 |
      | deadline[day]       | 1 |
      | deadline[month]     | January |
      | deadline[year]      | 2020 |
      | deadline[hour]      | 08 |
      | deadline[minute]    | 00 |
    And I press "Save"
    And I should see "Wednesday, 1 January 2020, 8:00"
    Then I click on "copy" "link"
    And I set the following fields to these values:
      | Override user  | Student2  |
      | deadline[year] | 2030 |
    And I press "Save"
    And I should see "Tuesday, 1 January 2030, 8:00"
    And I should see "Sam2 Student2"

  Scenario: Allow a single user to have re-take the escape
    When I follow "Test escape name"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | Re-takes allowed | 0 |
    And I press "Save and display"
    And I navigate to "User overrides" in current page administration
    And I press "Add user override"
    And I set the following fields to these values:
      | Override user    | Student1  |
      | Re-takes allowed | 1 |
    And I press "Save"
    And I should see "Re-takes allowed"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "Cat is an amphibian"
    And I set the following fields to these values:
      | False | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "Congratulations - end of escape reached"
    And I follow "Test escape name"
    Then I should not see "You are not allowed to retake this escape."
    And I should see "Cat is an amphibian"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "Cat is an amphibian"
    And I set the following fields to these values:
      | False | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "Congratulations - end of escape reached"
    And I follow "Test escape name"
    And I should see "You are not allowed to retake this escape."

  Scenario: Allow a single user to have a different password
    When I follow "Test escape name"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | Password protected escape | Yes |
      | id_password               | moodle_rules |
    And I press "Save and display"
    And I navigate to "User overrides" in current page administration
    And I press "Add user override"
    And I set the following fields to these values:
      | Override user             | Student1  |
      | Password protected escape | 12345 |
    And I press "Save"
    And I should see "Password protected escape"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    Then I should see "Test escape name is a password protected escape"
    And I should not see "Cat is an amphibian"
    And I set the field "userpassword" to "moodle_rules"
    And I press "Continue"
    And I should see "Login failed, please try again..."
    And I should see "Test escape name is a password protected escape"
    And I set the field "userpassword" to "12345"
    And I press "Continue"
    And I should see "Cat is an amphibian"
    And I set the following fields to these values:
      | False | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "Congratulations - end of escape reached"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "Test escape name is a password protected escape"
    And I should not see "Cat is an amphibian"
    And I set the field "userpassword" to "12345"
    And I press "Continue"
    And I should see "Login failed, please try again..."
    And I should see "Test escape name is a password protected escape"
    And I set the field "userpassword" to "moodle_rules"
    And I press "Continue"

  Scenario: Allow a user to have a different due date
    When I follow "Test escape name"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | id_deadline_enabled | 1 |
      | deadline[day]       | 1 |
      | deadline[month]     | January |
      | deadline[year]      | 2000 |
      | deadline[hour]      | 08 |
      | deadline[minute]    | 00 |
    And I press "Save and display"
    And I navigate to "User overrides" in current page administration
    And I press "Add user override"
    And I set the following fields to these values:
      | Override user       | Student1 |
      | id_deadline_enabled | 1 |
      | deadline[day]       | 1 |
      | deadline[month]     | January |
      | deadline[year]      | 2020 |
      | deadline[hour]      | 08 |
      | deadline[minute]    | 00 |
    And I press "Save"
    And I should see "Escape closes"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test escape"
    Then I should see "This escape closed on Saturday, 1 January 2000, 8:00"
    And I should not see "Cat is an amphibian"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape"
    And I should see "Cat is an amphibian"

  Scenario: Allow a user to have a different start date
    When I follow "Test escape name"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | id_available_enabled | 1 |
      | available[day]       | 1 |
      | available[month]     | January |
      | available[year]      | 2020 |
      | available[hour]      | 08 |
      | available[minute]    | 00 |
    And I press "Save and display"
    And I navigate to "User overrides" in current page administration
    And I press "Add user override"
    And I set the following fields to these values:
      | Override user        | Student1 |
      | id_available_enabled | 1 |
      | available[day]       | 1 |
      | available[month]     | January |
      | available[year]      | 2015 |
      | available[hour]      | 08 |
      | available[minute]    | 00 |
    And I press "Save"
    And I should see "Escape opens"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test escape"
    Then  I should see "This escape will be open on Wednesday, 1 January 2020, 8:00"
    And I should not see "Cat is an amphibian"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape"
    And I should see "Cat is an amphibian"

  Scenario: Allow a single user to have multiple attempts at each question
    When I follow "Test escape name"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | Re-takes allowed | 1 |
    And I press "Save and display"
    And I navigate to "User overrides" in current page administration
    And I press "Add user override"
    And I set the following fields to these values:
      | Override user              | Student1  |
      | Maximum number of attempts | 2 |
    And I press "Save"
    And I should see "Maximum number of attempts"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "Cat is an amphibian"
    And I set the following fields to these values:
      | True | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "Cat is an amphibian"
    And I set the following fields to these values:
      | True | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "Congratulations - end of escape reached"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "Cat is an amphibian"
    And I set the following fields to these values:
      | True | 1 |
    And I press "Submit"
    Then I press "Continue"
    And I should see "Congratulations - end of escape reached"

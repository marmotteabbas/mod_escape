@mod @mod_escape
Feature: In Dashboard, a student can see their current status on all escapes with an upcoming due date
  In order to know my status on a escape
  As a student
  I need to see it in Dashboard

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
    And the following "activities" exist:
      | activity | name             | intro                   | deadline   | retake | course | idnumber |
      | escape   | Test escape name | Test escape description | 1893481200 | 1      | C1     | escape1  |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on

  Scenario: A completed escape with only questions that allows multiple attempts
    Given I follow "Test escape name"
    And I follow "Add a question page"
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question 1 |
      | Page contents | Cat is an amphibian |
      | id_answer_editor_0 | False |
      | id_response_editor_0 | Correct |
      | id_jumpto_0 | Next page |
      | id_answer_editor_1 | True |
      | id_response_editor_1 | Wrong |
      | id_jumpto_1 | This page |
    And I press "Save page"
    And I select "Add a question page" from the "qtype" singleselect
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question 2 |
      | Page contents | Paper is made from trees. |
      | id_answer_editor_0 | True |
      | id_response_editor_0 | Correct |
      | id_jumpto_0 | Next page |
      | id_answer_editor_1 | False |
      | id_response_editor_1 | Wrong |
      | id_jumpto_1 | This page |
    And I press "Save page"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "Cat is an amphibian"
    And I set the following fields to these values:
      | False | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "Paper is made from trees."
    And I set the following fields to these values:
      | False | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "Congratulations - end of escape reached"

  Scenario: A completed escape with only questions that does not allow multiple attempts
    Given  I follow "Test escape name"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | Re-takes allowed | 0 |
    And I press "Save and display"
    And I follow "Add a question page"
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question 1 |
      | Page contents | Cat is an amphibian |
      | id_answer_editor_0 | False |
      | id_response_editor_0 | Correct |
      | id_jumpto_0 | Next page |
      | id_answer_editor_1 | True |
      | id_response_editor_1 | Wrong |
      | id_jumpto_1 | This page |
    And I press "Save page"
    And I select "Add a question page" from the "qtype" singleselect
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question 2 |
      | Page contents | Paper is made from trees. |
      | id_answer_editor_0 | True |
      | id_response_editor_0 | Correct |
      | id_jumpto_0 | Next page |
      | id_answer_editor_1 | False |
      | id_response_editor_1 | Wrong |
      | id_jumpto_1 | This page |
    And I press "Save page"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "Cat is an amphibian"
    And I set the following fields to these values:
      | False | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "Paper is made from trees."
    And I set the following fields to these values:
      | False | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "Congratulations - end of escape reached"
    And I log out

  Scenario: A completed escape with only content pages that allows multiple attempts
    Given I follow "Test escape name"
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
      | id_answer_editor_1 | End of escape |
      | id_jumpto_1 | End of escape |
    And I press "Save page"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "First page contents"
    And I press "Next page"
    And I should see "Second page contents"
    And I press "End of escape"
    And I log out

  Scenario: A completed escape with only content pages that does not allow multiple attempts
    Given I follow "Test escape name"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | Re-takes allowed | 0 |
    And I press "Save and display"
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
      | id_answer_editor_1 | End of escape |
      | id_jumpto_1 | End of escape |
    And I press "Save page"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "First page contents"
    And I press "Next page"
    And I should see "Second page contents"
    And I press "End of escape"
    And I log out

  Scenario: An incomplete escape with only questions.
    Given I follow "Test escape name"
    And I follow "Add a question page"
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question 1 |
      | Page contents | Cat is an amphibian |
      | id_answer_editor_0 | False |
      | id_response_editor_0 | Correct |
      | id_jumpto_0 | Next page |
      | id_answer_editor_1 | True |
      | id_response_editor_1 | Wrong |
      | id_jumpto_1 | This page |
    And I press "Save page"
    And I select "Add a question page" from the "qtype" singleselect
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question 2 |
      | Page contents | Paper is made from trees. |
      | id_answer_editor_0 | True |
      | id_response_editor_0 | Correct |
      | id_jumpto_0 | Next page |
      | id_answer_editor_1 | False |
      | id_response_editor_1 | Wrong |
      | id_jumpto_1 | This page |
    And I press "Save page"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "Cat is an amphibian"
    And I set the following fields to these values:
      | False | 1 |
    And I press "Submit"
    And I press "Continue"
    And I log out

  Scenario: An incomplete escape with only content pages.
    Given I follow "Test escape name"
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
      | id_answer_editor_1 | End of escape |
      | id_jumpto_1 | End of escape |
    And I press "Save page"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "First page contents"
    And I press "Next page"
    And I should see "Second page contents"
    And I log out

  Scenario: A escape with only questions that has not been started.
    Given I follow "Test escape name"
    And I follow "Add a question page"
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question 1 |
      | Page contents | Cat is an amphibian |
      | id_answer_editor_0 | False |
      | id_response_editor_0 | Correct |
      | id_jumpto_0 | Next page |
      | id_answer_editor_1 | True |
      | id_response_editor_1 | Wrong |
      | id_jumpto_1 | This page |
    And I press "Save page"
    And I select "Add a question page" from the "qtype" singleselect
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question 2 |
      | Page contents | Paper is made from trees. |
      | id_answer_editor_0 | True |
      | id_response_editor_0 | Correct |
      | id_jumpto_0 | Next page |
      | id_answer_editor_1 | False |
      | id_response_editor_1 | Wrong |
      | id_jumpto_1 | This page |
    And I press "Save page"
    And I log out

  Scenario: A escape with only content pages that has not been started.
    Given I follow "Test escape name"
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
      | id_answer_editor_1 | End of escape |
      | id_jumpto_1 | End of escape |
    And I press "Save page"
    And I log out

  Scenario: Viewing the status for multiple escapes in multiple courses
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 2 | C2 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C2 | editingteacher |
      | student1 | C2 | student |
    And the following "activities" exist:
      | activity | name               | intro                   | deadline   | retake | course | idnumber |
      | escape   | Test escape name 2 | Test escape description | 1893481200 | 1      | C1     | escape1  |
      | escape   | Test escape name 3 | Test escape description | 1893481200 | 1      | C2     | escape1  |
    And I turn editing mode off
    And I follow "Test escape name"
    And I follow "Add a question page"
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question |
      | Page contents | D035 M00d13 r0x0rz j00 b0x0rs? |
      | id_answer_editor_0 | True |
      | id_answer_editor_1 | False |
    And I press "Save page"
    And I am on "Course 1" course homepage
    And I follow "Test escape name 2"
    And I follow "Add a question page"
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question |
      | Page contents | D035 M00d13 r0x0rz j00 b0x0rs? |
      | id_answer_editor_0 | True |
      | id_answer_editor_1 | False |
    And I press "Save page"
    And I am on "Course 2" course homepage
    And I follow "Test escape name 3"
    And I follow "Add a question page"
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question 1 |
      | Page contents | D035 M00d13 r0x0rz j00 b0x0rs? |
      | id_answer_editor_0 | True |
      | id_answer_editor_1 | False |
    And I press "Save page"
    And I select "Add a question page" from the "qtype" singleselect
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question 2 |
      | Page contents | D035 M00d13 r0x0rz j00 b0x0rs? |
      | id_answer_editor_0 | True |
      | id_answer_editor_1 | False |
    And I press "Save page"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "D035 M00d13 r0x0rz j00 b0x0rs?"
    And I set the following fields to these values:
      | True | 1 |
    And I press "Submit"
    And I am on "Course 2" course homepage
    And I follow "Test escape name 3"
    And I should see "D035 M00d13 r0x0rz j00 b0x0rs?"
    And I set the following fields to these values:
      | True | 1 |
    And I press "Submit"
    And I log out
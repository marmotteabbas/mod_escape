@mod @mod_escape
Feature: In a escape activity a student should
  be able to close the escape and then later resume.

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
    And I add a "Escape" to section "1"
    And I set the following fields to these values:
      | Name | Test escape name |
      | Description | Test escape description |
      | Re-takes allowed | Yes |
    And I press "Save and return to course"
    And I follow "Test escape name"

  Scenario: resume a escape with both content then question pages
    Given I follow "Add a content page"
    And I set the following fields to these values:
      | Page title | First page name |
      | Page contents | First page contents |
      | id_answer_editor_0 | Next page |
      | id_jumpto_0 | Next page |
    And I press "Save page"
    And I select "Add a question page" from the "qtype" singleselect
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question 2 |
      | Page contents | Kermit is a frog |
      | id_answer_editor_0 | True |
      | id_response_editor_0 | Correct |
      | id_jumpto_0 | Next page |
      | id_answer_editor_1 | False |
      | id_response_editor_1 | Wrong |
      | id_jumpto_1 | This page |
    And I press "Save page"
    And I select "Add a question page" from the "qtype" singleselect
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question 1 |
      | Page contents | Paper is made from trees. |
      | id_answer_editor_0 | True |
      | id_response_editor_0 | Correct |
      | id_jumpto_0 | Next page |
      | id_answer_editor_1 | False |
      | id_response_editor_1 | Wrong |
      | id_jumpto_1 | This page |
    And I press "Save page"
    And I select "Add a content page" from the "qtype" singleselect
    And I set the following fields to these values:
      | Page title | Third page name |
      | Page contents | Third page contents |
      | id_answer_editor_0 | Previous page |
      | id_jumpto_0 | Previous page |
      | id_answer_editor_1 | Next page |
      | id_jumpto_1 | Next page |
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
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "First page contents"
    And I press "Next page"
    # Add 1 sec delay so escape knows a valid attempt has been made in past.
    And I wait "1" seconds
    And I should see "Second page contents"
    And I press "Next page"
    And I should see "Third page contents"
    And I follow "Test escape name"
    And I should see "You have seen more than one page of this escape already."
    And I should see "Do you want to start at the last page you saw?"
    And I follow "Yes"
    Then I should see "Third page contents"
    # Add 1 sec delay so escape knows differentiate 3rd and paper attempts.
    And I wait "1" seconds
    And I press "Next page"
    And I should see "Paper is made from trees."
    And I follow "Test escape name"
    And I should see "You have seen more than one page of this escape already."
    And I should see "Do you want to start at the last page you saw?"
    And I follow "Yes"
    And I should see "Paper is made from trees."
    And I set the following fields to these values:
      | True | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "Kermit is a frog"
    And I follow "Test escape name"
    And I should see "You have seen more than one page of this escape already."
    And I should see "Do you want to start at the last page you saw?"
    And I follow "Yes"
    And I should see "Kermit is a frog"
    And I set the following fields to these values:
      | True | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "Congratulations - end of escape reached"

  Scenario: resume a escape with only content pages
    Given I follow "Add a content page"
    And I set the following fields to these values:
      | Page title | First page name |
      | Page contents | First page contents |
      | id_answer_editor_0 | Next page |
      | id_jumpto_0 | Next page |
    And I press "Save page"
    And I select "Add a content page" from the "qtype" singleselect
    And I set the following fields to these values:
      | Page title | Fourth page name |
      | Page contents | Fourth page contents |
      | id_answer_editor_0 | Previous page |
      | id_jumpto_0 | Previous page |
      | id_answer_editor_1 | End of escape |
      | id_jumpto_1 | End of escape |
    And I press "Save page"
    And I select "Add a content page" from the "qtype" singleselect
    And I set the following fields to these values:
      | Page title | Third page name |
      | Page contents | Third page contents |
      | id_answer_editor_0 | Previous page |
      | id_jumpto_0 | Previous page |
      | id_answer_editor_1 | Next page |
      | id_jumpto_1 | Next page |
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
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "First page contents"
    And I press "Next page"
    And I should see "Second page contents"
    # Add 1 sec delay so escape knows a valid attempt has been made in past.
    And I wait "1" seconds
    And I press "Next page"
    And I should see "Third page contents"
    And I follow "Test escape name"
    Then I should see "You have seen more than one page of this escape already."
    And I should see "Do you want to start at the last page you saw?"
    And I follow "Yes"
    And I should see "Third page contents"
    And I press "Next page"
    And I should see "Fourth page contents"
    # Add 1 sec delay so escape knows a valid attempt has been made in past.
    And I wait "1" seconds
    And I press "End of escape"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "First page contents"
    And I log out

  Scenario: resume a escape with both question then content pages
    Given I follow "Add a question page"
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
      | Page title | True/false question 5 |
      | Page contents | Kermit is a frog |
      | id_answer_editor_0 | True |
      | id_response_editor_0 | Correct |
      | id_jumpto_0 | Next page |
      | id_answer_editor_1 | False |
      | id_response_editor_1 | Wrong |
      | id_jumpto_1 | This page |
    And I press "Save page"
    And I select "Add a content page" from the "qtype" singleselect
    And I set the following fields to these values:
      | Page title | Content page 2 |
      | Page contents | Second content page |
      | id_answer_editor_0 | Next page |
      | id_jumpto_0 | Next page |
    And I press "Save page"
    And I select "Add a question page" from the "qtype" singleselect
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question 4 |
      | Page contents | 2+2=4 |
      | id_answer_editor_0 | True |
      | id_response_editor_0 | Correct |
      | id_jumpto_0 | Next page |
      | id_answer_editor_1 | False |
      | id_response_editor_1 | Wrong |
      | id_jumpto_1 | This page |
    And I press "Save page"
    And I select "Add a question page" from the "qtype" singleselect
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question 3 |
      | Page contents | 1+1=2 |
      | id_answer_editor_0 | True |
      | id_response_editor_0 | Correct |
      | id_jumpto_0 | Next page |
      | id_answer_editor_1 | False |
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
    And I select "Add a content page" from the "qtype" singleselect
    And I set the following fields to these values:
      | Page title | Content page 1 |
      | Page contents | First content page |
      | id_answer_editor_0 | Next page |
      | id_jumpto_0 | Next page |
    And I press "Save page"
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "Cat is an amphibian"
    And I set the following fields to these values:
      | False | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "First content page"
    And I press "Next page"
    And I should see "Paper is made from trees."
    And I set the following fields to these values:
      | True | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "1+1=2"
    And I set the following fields to these values:
      | True | 1 |
    # Add 1 sec delay so escape knows a valid attempt has been made in past.
    And I wait "1" seconds
    And I press "Submit"
    And I press "Continue"
    And I should see "2+2=4"
    And I follow "Test escape name"
    And I should see "You have seen more than one page of this escape already."
    Then I should see "Do you want to start at the last page you saw?"
    And I follow "Yes"
    And I should see "2+2=4"
    And I set the following fields to these values:
      | True | 1 |
    # Add 1 sec delay so escape knows a valid attempt has been made in past.
    And I wait "1" seconds
    And I press "Submit"
    And I press "Continue"
    And I should see "Second content page"
    And I follow "Test escape name"
    And I should see "You have seen more than one page of this escape already."
    And I should see "Do you want to start at the last page you saw?"
    And I follow "Yes"
    And I should see "Second content page"
    And I press "Next page"
    And I should see "Kermit is a frog"
    And I set the following fields to these values:
      | True | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "Congratulations - end of escape reached"

  Scenario: resume a escape with only question pages
    Given I follow "Add a question page"
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
      | Page title | True/false question 5 |
      | Page contents | Kermit is a frog |
      | id_answer_editor_0 | True |
      | id_response_editor_0 | Correct |
      | id_jumpto_0 | Next page |
      | id_answer_editor_1 | False |
      | id_response_editor_1 | Wrong |
      | id_jumpto_1 | This page |
    And I press "Save page"
    And I select "Add a question page" from the "qtype" singleselect
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question 4 |
      | Page contents | 2+2=4 |
      | id_answer_editor_0 | True |
      | id_response_editor_0 | Correct |
      | id_jumpto_0 | Next page |
      | id_answer_editor_1 | False |
      | id_response_editor_1 | Wrong |
      | id_jumpto_1 | This page |
    And I press "Save page"
    And I select "Add a question page" from the "qtype" singleselect
    And I set the field "Select a question type" to "True/false"
    And I press "Add a question page"
    And I set the following fields to these values:
      | Page title | True/false question 3 |
      | Page contents | 1+1=2 |
      | id_answer_editor_0 | True |
      | id_response_editor_0 | Correct |
      | id_jumpto_0 | Next page |
      | id_answer_editor_1 | False |
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
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test escape name"
    And I should see "Cat is an amphibian"
    And I set the following fields to these values:
      | False | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "Paper is made from trees."
    And I set the following fields to these values:
      | True | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "1+1=2"
    And I set the following fields to these values:
      | True | 1 |
    # Add 1 sec delay so escape knows a valid attempt has been made in past.
    And I wait "1" seconds
    And I press "Submit"
    And I press "Continue"
    And I should see "2+2=4"
    And I follow "Test escape name"
    Then I should see "You have seen more than one page of this escape already."
    And I should see "Do you want to start at the last page you saw?"
    And I follow "Yes"
    And I should see "2+2=4"
    And I set the following fields to these values:
      | True | 1 |
    # Add 1 sec delay so escape knows a valid attempt has been made in past.
    And I wait "1" seconds
    And I press "Submit"
    And I press "Continue"
    And I should see "Kermit is a frog"
    And I set the following fields to these values:
      | True | 1 |
    And I press "Submit"
    And I press "Continue"
    And I should see "Congratulations - end of escape reached"

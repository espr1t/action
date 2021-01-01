# How to run tests

## Unit tests
Just start the system and access http://localhost/admin/tests/language-detector

## PROD tests
Running the language detector on submissions from the database happens by appending a start index to the link above:
http://localhost/admin/tests/language-detector/42. The system tests 1000 submissions starting from the given id.

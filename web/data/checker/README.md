# Checkers
For tasks with multiple possible outputs or tasks with relative scoring the authors must prepare a program which should evaluate contestant's submissions (whether their output is correct/valid and how much points it should get).

The system currently supports only C++ checkers, which should work as follows.

## Input
The checker is provided three arguments:
1. The filename of the test's input (in)
2. The filename of the contestant's output (out)
3. The filename of the author's output (sol)

## Checker or System Error
In case there is a problem with the checker itself or the stystem (e.g., some of the input files is missing or cannot be read) it is expected to exit with a non-zero exit code. The stdout stream is ignored and the stderr is returned to the frontend.

## Expected output
In case everything goes well (that is, the checker ran smoothly) exit the checker with exit code 0 and print on the stdout stream one or two lines:
1. The score for the test (a number between 0.0 and 1.0). Don't worry that the score is in
[0, 1] - this is later scaled.
2. Optional message what was the problem with the answer.

## Example checker
An example stub of a checker is provided in this directory.
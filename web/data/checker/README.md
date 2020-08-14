# Checkers
For tasks with multiple possible outputs or tasks with relative scoring the authors must prepare a program which should evaluate contestant's submissions (whether their output is correct/valid and how much points it should get).

The system currently supports only C++ checkers, which should work as follows.

## Input
The checker is provided three arguments:
1. The filename of the test's input (in)
2. The filename of the contestant's output (out)
3. The filename of the author's output (sol)

## Checker or System Error
In case there is a problem with the checker itself or the stystem (e.g., some of the input files is missing or cannot be read) the verdict is expected to be IE (Internal Error).

## Expected output
In case everything goes well (that is, the checker ran smoothly) print on the stderr stream two or three lines:
1. Line 1: The verdict. Accepted answers expect verdict "OK" (everything was okay). Problems with the checker/tester or system should be "IE" (Internal Error). Answers considered wrong should be marked as "WA" (Wrong Answer).
1. Line 2: The score for the test (or a result, in case of relatively scored problems). In case of a score, print a number between 0.0 and 1.0 - this is later scaled to the actual test score.
1. Line 3: Optional message (returned to the user).

## Example checker
```C++
/*
TASK: TaskName
KEYWORDS: Checker
*/

#include <cstdio>
#include <cstdlib>

void finalVerdict(const char* verdict, double score, const char* message = "") {
    fprintf(stderr, "%s\n", verdict);
    fprintf(stderr, "%lf\n", score);
    fprintf(stderr, "%s\n", message);
    exit(0);
}

int main(int argc, char* argv[]) {
    if (argc < 4) {
        finalVerdict("IE", 0.0, "Invalid number of arguments!");
    }

    // argv[1] is the name of task's input file
    FILE* in = fopen(argv[1], "rt");
    if (in == NULL) {
        finalVerdict("IE", 0.0, "Could not open task's input file!");
    }

    // argv[2] is the name of contestant's output file
    FILE* out = fopen(argv[2], "rt");
    if (out == NULL) {
        finalVerdict("IE", 0.0, "Could not open contestant's output file!");
    }

    // argv[3] is the name of author's output file
    FILE* sol = fopen(argv[3], "rt");
    if (sol == NULL) {
        finalVerdict("IE", 0.0, "Could not open author's output file!");
    }

    // Read Input

    // Read contestant's output

    // Optionally read author's output

    // Calculate score
    double score = 0.42;

    // TODO...

    finalVerdict("OK", score, "Optional message.");
    return 0;
}
```

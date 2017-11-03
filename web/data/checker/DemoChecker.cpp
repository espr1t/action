/*
ID: espr1t
TASK: TaskName
KEYWORDS: Checker
INSTRUCTIONS:
    The checker is provided three arguments:
        1. The filename of the test's input (in)
        2. The filename of the contestant's output (out)
        3. The filename of the author's output (sol)
    In case there is a problem with the checker itself or the stystem
    (e.g., some of the input files is missing or cannot be read) it is
    expected to exit with a non-zero exit code. The stdout stream is
    ignored and the stderr is returned.
    In case everything goes well (that is, the checker ran smoothly)
    exit the checker with exit code 0 and print on the stdout stream
    one or two lines:
        1. The score for the test (a number between 0.0 and 1.0).
        Don't worry that the score is in [0, 1] - this is later scaled.
        2. Optional message what was the problem with the answer.
*/

#include <cstdio>
#include <cstdlib>
#include <algorithm>
using namespace std;


int main(int argc, char* argv[]) {
    if (argc < 4) {
        fprintf(stderr, "Invalid number of arguments!\n");
        return 1;
    }
    
    // argv[1] is the name of task's input file
    FILE* in = fopen(argv[1], "rt");
    if (in == NULL) {
        fprintf(stderr, "ERROR: Could not open task's input file!\n");
        exit(-1);
    }

    // argv[2] is the name of contestant's output file
    FILE* out = fopen(argv[2], "rt");
    if (out == NULL) {
        fprintf(stderr, "ERROR: Could not open contestant's output file!\n");
        exit(-1);
    }
    
    // argv[3] is the name of author's output file
    FILE* sol = fopen(argv[3], "rt");
    if (sol == NULL) {
        fprintf(stderr, "ERROR: Could not open author's output file!\n");
        exit(-1);
    }
    
    // Read Input
    
    // Read contestant's output
    
    // Optionally read author's output
    
    // Calculate score
    double score = 0.42;

    // TODO...

    fprintf(stdout, "%lf\n", min(1.0, max(0.0, score)));
    fprintf(stdout, "OK\n");
	return 0;
}


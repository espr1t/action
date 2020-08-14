/*
ID: espr1t
TASK: Number Guessing
KEYWORDS: Tester
*/

#include <cstdio>
#include <cstring>
#include <cstdlib>

FILE* log;

void finalVerdict(const char* verdict, double score, const char* message) {
    fprintf(stderr, "%s\n", verdict);
    fprintf(stderr, "%lf\n", score);
    fprintf(stderr, "%s\n", message);
    exit(0);
}

int readQuery() {
    int guess;
    char buff[1024];
    memset(buff, 0, sizeof(buff));
    fgets(buff, 1000, stdin);
    if (strlen(buff) > 6) {
        fprintf(log, "WA: printed query was longer than expected.\n");
        finalVerdict("WA", 0.0, "Printed query was longer than expected.");
    }
    if (sscanf(buff, "%d", &guess) != 1) {
        fprintf(log, "WA: printed query was not an integer.\n");
        finalVerdict("WA", 0.0, "Printed query was not an integer.");
    }
    if (guess < 1 || guess > 1000) {
        fprintf(log, "WA: printed query was not in range [1, 1000].\n");
        finalVerdict("WA", 0.0, "Printed query was not in range [1, 1000].");
    }
    return guess;
}

void printResponse(const char* message) {
    fprintf(stdout, "%s\n", message);
    fflush(stdout);
}

int main(int argc, char** argv) {
    log = fopen(argv[1], "wt");

    int answer;
    fscanf(stdin, "%d", &answer);
    fprintf(log, "Target number: %d\n", answer);

    // Read the new line after the input
    char buff[1024];
    fgets(buff, 1024, stdin);

    int guesses = 0;
    bool guessed = false;
    while (guesses++ < 10) {
        int guess = readQuery();
        if (guess < answer) {
            printResponse("Smaller.");
            fprintf(log, "Query for %d (smaller).\n", guess);
        } else if (guess > answer) {
            printResponse("Larger.");
            fprintf(log, "Query for %d (larger).\n", guess);
        } else {
            printResponse("Correct!");
            fprintf(log, "Query for %d (correct).\n", guess);
            guessed = true;
            break;
        } 
    }

    if (guessed) {
        char message[1024];
        fprintf(log, "Guessed correctly with %d queries.", guesses);
        sprintf(message, "Guessed correctly with %d queries.", guesses);
        finalVerdict("OK", 1.0, message);
    } else {
        fprintf(log, "Couldn't guess after 10 queries.");
        finalVerdict("WA", 0.0, "Couldn't guess after 10 queries.");
    }

    return 0;
}

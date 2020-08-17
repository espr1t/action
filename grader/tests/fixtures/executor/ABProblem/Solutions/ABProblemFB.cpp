/*
ID: espr1t
TASK: ABProblem
KEYWORDS: Trivial, Implementation
*/

#include <cstdio>
#include <unistd.h>

FILE* in = stdin; FILE* out = stdout;

int main(void) {
    double a, b;
    fscanf(in, "%lf %lf", &a, &b);

    int forkCycles = (a >= 0 && b >= 0) ? 3 : 1000;
    for (int i = 0; i < forkCycles; i++) {
        int id = fork();
        if (id < 0) {
            fprintf(out, "Cannot fork!\n");
        }
        if (i == 0 && id > 0) {
            // Sleep for 0.2 seconds so "Cannot fork!" is printed first.
            usleep(200000);
            fprintf(out, "%.9lf", a * b);
            fflush(out);
        }
    }
    
    return 0;
}


/*
ID: espr1t
TASK: ABProblem
KEYWORDS: Trivial, Implementation
*/

#include <cstdio>
FILE* in = stdin; FILE* out = stdout;

int main(void) {
    double a, b;
    fscanf(in, "%lf %lf", &a, &b);
    fprintf(out, "%.9lf\n", a * b);
    if (a < 0 || b < 0) {
        fprintf(out, "Negative numbers!\n");
    }
    return 0;
}

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

    return 0;
}

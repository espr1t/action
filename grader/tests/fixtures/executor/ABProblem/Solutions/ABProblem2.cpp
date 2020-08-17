/*
ID: espr1t
TASK: ABProblem
KEYWORDS: Trivial, Implementation
*/

#include <cstdio>
FILE* in = stdin; FILE* out = stdout;

int main(void) {
    long double a, b;
    fscanf(in, "%Lf %Lf", &a, &b);
    fprintf(out, "%.13Lf\n", a * b);

    return 0;
}

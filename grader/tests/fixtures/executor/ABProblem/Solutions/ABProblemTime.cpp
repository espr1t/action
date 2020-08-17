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
    fprintf(out, "%.9lf\n", a * b);
    fflush(out);
    
    if (a < 0 || b < 0) {
        // Spawn 5 children from the main process
        for (int i = 0; i < 10; i++) {
            if (!fork()) {
                break;
            }
        }
    }
    
    // Each of the processes does some work.
    // We should verify that their total time is counted.
    long long num = getpid(), sum = 0;
    for (int i = 0; i < 50000000; i++) {
        sum += num;
        num = (num * 1234567 + 426661337) % 1000000007;
    }
    fprintf(stderr, "%lld\n", sum);
    return 0;
}

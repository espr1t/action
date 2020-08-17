/*
ID: espr1t
TASK: ABProblem
KEYWORDS: Trivial, Implementation
*/

#include <cstdio>
#include <cmath>

FILE* in = stdin; FILE* out = stdout;

const int MAX = 4194304;

int arr[MAX];
int offset[3] = {1, 100, 100000};
double as[3] = {42.42, -1337.12345678, 5};

int main(void) {
    double a, b;
    fscanf(in, "%lf %lf", &a, &b);
    fprintf(out, "%.9lf\n", a * b);
    
    int test = -1;
    for (int i = 0; i < 3; i++)
        if (fabs(a - as[i]) < 0.001) test = i;
    
    for (int i = 0; i < MAX; i++)
        arr[i] = i;
    fprintf(stderr, "%d\n", arr[MAX - 1 + offset[test]]);
    arr[MAX - 1 + offset[test]] = 42;
    fprintf(stderr, "%d\n", arr[MAX - 1 + offset[test]]);

    return 0;
}

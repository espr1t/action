/*
ID: espr1t
TASK: ABProblem
KEYWORDS: Trivial, Implementation
*/

#include <cstdio>
#include <unistd.h>
#include <random>

using namespace std;
FILE* in = stdin; FILE* out = stdout;

mt19937 mt;
int randd() {
    int ret = mt();
    return ret < 0 ? -ret : ret;
}

int main(void) {
    double a, b;
    fscanf(in, "%lf %lf", &a, &b);

    int id = fork();
    if (id < 0) {
        fprintf(out, "Cannot fork!\n");
    }
    fprintf(out, "%.9lf\n", a * b);
    
    return 0;
}

/*
ID: espr1t
TASK: ABProblem
KEYWORDS: Trivial, Implementation
*/

#include <cstdio>
#include <cstdlib>
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
    if (a < 0 || b < 0) {
        static char buff[1048576];
        for (int i = 0; i < 1000000; i++)
            buff[i] = '0' + randd() % 10;
        for (int i = 0; i < 1000; i++)
            if (fprintf(out, "%s\n", buff) < 1000000)
                exit(-1);
    }
    fprintf(out, "%.9lf\n", a * b);

    return 0;
}

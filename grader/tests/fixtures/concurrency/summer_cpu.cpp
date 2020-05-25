#include <cstdio>

int main(void) {
    int numIters;
    fscanf(stdin, "%d", &numIters);
    long long sum = 42, cur = 42;
    for (int i = 0; i < numIters; i++) {
        cur = (cur * 13 + 17) % 1000000007;
        sum += cur;
    }
    fprintf(stdout, "%lld\n", sum);
    return 0;
}

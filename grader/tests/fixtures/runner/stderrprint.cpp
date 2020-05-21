#include <cstdio>

int main(void) {
    long long sum = 0, iter = 0;
    while (iter < 1234567) {
        sum += iter++;
    }
    fprintf(stderr, "%lld\n", iter);
    fprintf(stdout, "%lld\n", sum);
    return 0;
}

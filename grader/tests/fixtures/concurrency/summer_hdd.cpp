#include <cstdio>

int main(void) {
    long long sum = 0;
    int cur;
    while (fscanf(stdin, "%d", &cur) == 1)
        sum += cur;
    fprintf(stdout, "%lld\n", sum);
    return 0;
}

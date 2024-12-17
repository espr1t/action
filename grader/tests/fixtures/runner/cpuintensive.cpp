#include <cstdio>
#include <ctime>

int main(void) {
    unsigned sTime = clock();
    long long sum = 0, iter = 0;
    while (true) {
        sum += iter++;
        if (iter % 1000000 == 0) {
            if ((double)(clock() - sTime) / CLOCKS_PER_SEC > 0.3)
                break;
        }
    }
    printf("%lld %lld\n", iter, sum);
    return 0;
}

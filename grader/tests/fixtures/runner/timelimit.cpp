#include <cstdio>

int main(void) {
    unsigned cur = 42;
    unsigned xr = cur;
    for (int i = 1; i < 2000000000; i++) {
        cur = cur * 13 + 17;
        xr ^= cur;
    }
    printf("%u\n", xr);
    return 0;
}

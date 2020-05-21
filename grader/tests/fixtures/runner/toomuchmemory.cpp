#include <cstdio>
#include <vector>

int main(void) {
    std::vector <unsigned> v(600000000);
    v[0] = 42;
    unsigned xr = v[0];
    for (int i = 1; i < (int)v.size(); i++) {
        v[i] = v[i - 1] * 13 + 17;
        xr ^= v[i];
    }
    printf("%u\n", xr);
    return 0;
}

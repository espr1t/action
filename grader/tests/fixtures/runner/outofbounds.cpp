#include <cstdio>

int main(void) {
    int a[10];
    for (int i = 0; i < 100; i++)
        a[i] = a[i - 1] * 13 + 17;
    printf("%d\n", a[5]);
    return 0;
}

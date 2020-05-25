#include <cstdio>
#include <cstdlib>

const int SIZE = 50000000;

int main(void) {
    static char a[SIZE + 1];
    for (int i = 0; i < SIZE; i++)
        a[i] = 'A' + rand() % 26;
    printf("%s\n", a);
    return 0;
}

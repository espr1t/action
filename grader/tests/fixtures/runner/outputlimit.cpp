#include <cstdio>
#include <cstdlib>

char a[20000002];

int main(void) {
    for (int i = 0; i < 20000000; i++)
        a[i] = 'A' + rand() % 26;
    printf("%s\n", a);
    return 0;
}

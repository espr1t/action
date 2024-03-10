#include <cstdio>
#include <cstring>
#include <algorithm>

int main(void) {
    char a[128];
    scanf("%s", a);
    std::reverse(a, a + strlen(a));
    printf("%s\n", a);
    return 0;
}

#include <cstdio>

int main(void) {
    FILE* out = fopen("foo.txt", "wt");
    fprintf(out, "42\n");
    fclose(out);
    return 0;
}

#include <cstdio>
int main(int argc, char** argv) {
    for (int i = 1; i < argc; i++)
        printf("%s", argv[i]);
    return 0;
}
#include <cstdio>
#include <cstring>
#include <vector>
using namespace std;

void allocateOnHeap(long long memory) {
    vector <unsigned> v(memory / 4);
    v[0] = 13;
    for (int i = 1; i < (int)v.size(); i++)
        v[i] = v[i - 1] * 17 + 1337;
    fprintf(stderr, "%u\n", v.back());
}

unsigned recurse(int memory, int level) {
    if (memory < 0)
        return level;
    unsigned a[1024];
    a[0] = recurse(memory - 1028 * 4, level + 1);
    for (int i = 1; i < 1024; i++)
        a[i] = a[i - 1] * 17 + 1337;
    return a[1023];
}

void allocateOnStack(int memory) {
    fprintf(stderr, "%u\n", recurse(memory, 0));
}

int main(int argc, char** argv) {
    if (argc < 3) {
        fprintf(stderr, "Arguments should be <heap|stack> <size>.\n");
        return 0;
    }
    long long memory;
    sscanf(argv[2], "%lld", &memory);
    if (!strcmp(argv[1], "heap")) {
        allocateOnHeap(memory);
    } else if (!strcmp(argv[1], "stack")) {
        allocateOnStack(memory);
    } else {
        fprintf(stderr, "Invalid argument %s (should be 'heap' or 'stack').\n", argv[1]);
    }
    return 0;
}

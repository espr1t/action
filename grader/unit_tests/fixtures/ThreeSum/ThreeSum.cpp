#include <cstdio>
#include <unistd.h>
#include <vector>
#include <algorithm>
#include <cstdlib>
using namespace std;

const int MOD = 1000000007;

vector <int> v;

void printWorkingDir() {
    char cCurrentPath[1024];
    getcwd(cCurrentPath, sizeof(cCurrentPath));
    fprintf(stdout, "%s\n", cCurrentPath);
}

int main(void) {
    // printWorkingDir();

    int n;
    fscanf(stdin, "%d", &n);

    // OK for N = 3 (expected: 90)
    // OK for N = 20 (expected: 1859550)
    // WA for N = 200 (expected: 569495340)
    // TL for N = 2000 (expected: 134460380)
    // RE for N = 20000 (expected: 718669707)
    // ML for N = 200000 (expected: 607935249)

    // RE for N = 13 (expected: 134542485) - trying to fork
    // RE for N = 42 (expected: 165620) - trying to write a file in the current directory
    // RE for N = 43 (expected: 165620) - trying to write a file in the home directory
    // RE for N = 666 (expected: 275429814) - trying to write too much output

    if (n == 13) {
        // If we cannot fork, print a message so we get a wrong answer
        if (fork() < 0) {
            fprintf(stdout, "Cannot fork!");
            return 0;
        }
    }

    if (n == 42) {
        FILE* out = fopen("foo.txt", "wt");
        fprintf(out, "boo!");
        fclose(out);
    }

    if (n == 666) {
        // RLIMIT for file size is 16777216 bytes, so write just slightly over this bound
        // Do it this way so we don't get time limit
        int size = 16800000 / 16;
        char* buff = new char[size];
        for (int i = 0; i < size; i++)
            buff[i] = 'a' + i % 26;
        buff[size - 1] = '\0';
        for (int i = 0; i < 16; i++)
            fprintf(stdout, "%s\n", buff);
    }

    if (n <= 20000) {
        int ans = 0;
        int a[2048];
        for (int i1 = 1; i1 <= n; i1++) {
            a[i1] = i1;
            for (int i2 = i1; i2 <= n; i2++) {
                a[i2] = i2;
                for (int i3 = i2; i3 <= n; i3++) {
                    a[i3] = i3;
                    ans += a[i1] * a[i2] * a[i3];
                }
            }
        }
        fprintf(stdout, "%d\n", ans);
    } else {
        // Causes a memory limit
        for (int i1 = 1; i1 <= n; i1++) {
            for (int i2 = i1; i2 <= n; i2++) {
                for (int i3 = i2; i3 <= n; i3++) {
                    v.push_back(i1 * i2 * i3);
                }
            }
        }
    }

    return 0;
}

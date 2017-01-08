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

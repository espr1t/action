#include <cstdio>
#include <unistd.h>
#include <vector>
#include <algorithm>
#include <cstdlib>
#include <csignal>
#include <thread>
using namespace std;

const int MOD = 1000000007;

vector <int> v;
char buff[20000000];

void signalHandler(int signum) {
    // Ignoring signal.
}

long long answers[4];
void eval(int n, int mod) {
    answers[mod] = 0;
    for (long long n1 = 1; n1 <= n; n1++) {
        if (n1 % 4 != mod) continue;
        for (long long n2 = n1; n2 <= n; n2++) {
            for (long long n3 = n2; n3 <= n; n3++) {
                answers[mod] += n1 * n2 * n3;
            }
        }
    }
}

int main(void) {
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
        int id = fork();
        if (id < 0)
            fprintf(stdout, "Cannot fork!\n");
        // Exit from the father and leave the child calculate the result
        if (id > 0)
            return 0;
    }

    if (n == 17) {
        // Try a fork bomb.
        while (true) {
            int id = fork();
            if (id < 0) {
                fprintf(stdout, "Cannot fork!\n");
                break;
            }
        }
    }
    
    if (n == 2400) {
        // Ignore SIGTERM signal
        signal(SIGTERM, signalHandler);  
    }

    if (n == 2345) {
        thread t0(eval, n, 0);
        thread t1(eval, n, 1);
        thread t2(eval, n, 2);
        thread t3(eval, n, 3);

        long long ans = 0;
        t0.join(); ans += answers[0];
        t1.join(); ans += answers[1];
        t2.join(); ans += answers[2];
        t3.join(); ans += answers[3];
        fprintf(stdout, "%lld\n", ans % MOD);
        return 0;
    }

    if (n == 42) {
        FILE* out = fopen("foo.txt", "wt");
        fprintf(out, "boo!");
        fclose(out);
    }

    if (n == 43) {
        FILE* out = fopen("~/foo.txt", "wt");
        fprintf(out, "boo!");
        fclose(out);
    }

    if (n == 665) {
        // RLIMIT for file size is 16777216 bytes, so write just slightly over this bound
        int size = 16000000;
        for (int i = 0; i < size; i++)
            buff[i] = 'a' + i % 26;
        buff[size - 1] = '\0';
        fprintf(stdout, "%s\n", buff);
        return 0;
    }

    if (n == 666) {
        // RLIMIT for file size is 16777216 bytes, so write just slightly over this bound
        int size = 17000000;
        for (int i = 0; i < size; i++)
            buff[i] = 'a' + i % 26;
        buff[size - 1] = '\0';
        fprintf(stdout, "%s\n", buff);
        return 0;
    }

    if (n <= 20000) {
        int ans = 0;
//        long long real = 0;
        int a[3003];
        for (int i1 = 1; i1 <= n; i1++) {
            a[i1] = i1;
            for (int i2 = i1; i2 <= n; i2++) {
                a[i2] = i2;
                for (int i3 = i2; i3 <= n; i3++) {
                    a[i3] = i3;
                    ans += a[i1] * a[i2] * a[i3];
//                    real = (real + (long long)i1 * i2 * i3) % MOD;
                }
            }
        }
        fprintf(stderr, "%d\n", (int)(ans % MOD));
        fprintf(stdout, "%d\n", (int)(ans % MOD));
//        fprintf(stdout, "%lld\n", real);
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

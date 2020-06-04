/*
ID: espr1t
TASK: ThreeSum
KEYWORDS: Medium, DP
*/

#include <cstdio>
using namespace std;

const int MAX = 200002;
const int MOD = 1000000007;
FILE* in = stdin; FILE* out = stdout;

int n;
int a[MAX];
int sum[MAX];

int main(void) {
    fscanf(in, "%d", &n);
    for (int i = 1; i <= n; i++)
        a[i] = i;

    for (int iter = 2; iter <= 3; iter++) {
        sum[n] = 0;
        for (int i = n; i > 0; i--) {
            sum[i] = (sum[i + 1] + a[i]) % MOD;
            a[i] = ((long long)i * sum[i]) % MOD;
        }
    }
    int ans = 0;
    for (int i = 1; i <= n; i++)
        ans = (ans + a[i]) % MOD;
    fprintf(out, "%d\n", ans);

    return 0;
}
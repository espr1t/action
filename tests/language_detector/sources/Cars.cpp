#include <cstdio>
#define MAXN 1024

int N;
int cars[MAXN];
int ans;

int resolveCycle(int needle, int idx) {
    if (cars[idx] == needle) {
        return idx;
    }

    int temp;
    int final = resolveCycle(needle, cars[idx]);
    temp = cars[idx];
    cars[idx] = cars[final];
    cars[final] = temp;

    ans++;

    return idx;
}

void solve() {
    int swaps = 0;
    int temp = 0;
    int prev = 0;

    if (cars[0] != 0) {
        resolveCycle(0, 0);
    }

    for (int i = 1; i <= N; i++) {
        if (cars[i] == i) {
            continue;
        }

        cars[0] = cars[i];
        cars[i] = 0;
        ans++;

        resolveCycle(0, 0);
    }
}

int main(void) {
    scanf("%d", &N);

    for (int i = 0; i <= N; i++) {
        scanf("%d", &cars[i]);
	}

    ans = 0;
    solve();

    printf("%d\n", ans * 10);

    return 0;
}

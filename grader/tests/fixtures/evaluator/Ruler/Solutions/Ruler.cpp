/*
ID: espr1t
TASK: Ruler
KEYWORDS: Hard, Bruteforce, Golomb Ruler
*/

#include <cstdio>
#include <ctime>
#include <vector>
#include <algorithm>

using namespace std;
FILE* in = stdin; FILE* out = stdout;

const int STATE_SIZE = 4;
const int MAX = STATE_SIZE * 64;

struct State {
    unsigned long long mask[STATE_SIZE];

    State() {
        for (int i = 0; i < STATE_SIZE; i++)
            mask[i] = 0;
    }

    State(const State& r) {
        for (int i = 0; i < STATE_SIZE; i++)
            mask[i] = r.mask[i];
    }

    void set(int idx) {
        if (idx >= 0 && idx < STATE_SIZE * 64) {
            mask[idx >> 6] |= (1ull << (idx & 63));
        }
    }

    bool get(int idx) {
        return !!(mask[idx >> 6] & (1ull << (idx & 63)));
    }

    bool intersect(const State& r) const {
        for (int i = 0; i < STATE_SIZE; i++)
            if (mask[i] & r.mask[i]) return true;
        return false;
    }

    void change(const State& r) {
        for (int i = 0; i < STATE_SIZE; i++)
            mask[i] ^= r.mask[i];
    }

    void shift() {
        for (int i = STATE_SIZE - 1; i >= 0; i--) {
            mask[i] <<= 1;
            if (i > 0) mask[i] |= !!(mask[i - 1] & (1ull << 63));
        }
    }
};

int n, m;
int nums[MAX], cnt;
int ans[MAX];

/*
=======================================
Without aggressive heuristics
=======================================
Length of ruler of size 13 is 106.
0 2 5 25 37 43 59 70 85 89 98 99 106
Calculated in 25.197s

Length of ruler of size 14 is 127.
0 4 6 20 35 52 59 77 78 86 89 99 122 127
Calculated in 364.430s

=======================================
With half elements on the left side.
=======================================
Length of ruler of size 13 is 106.
0 2 5 25 37 43 59 70 85 89 98 99 106
Calculated in 9.501s

Length of ruler of size 14 is 127.
0 4 6 20 35 52 59 77 78 86 89 99 122 127
Calculated in 64.268s

Length of ruler of size 15 is 151.
0 4 20 30 57 59 62 76 100 111 123 136 144 145 151
Calculated in 1988.452s

=======================================
With aggressive heuristics.
=======================================
Length of ruler of size 13 is 106.
0 2 5 25 37 43 59 70 85 89 98 99 106
Calculated in 6.522s

Length of ruler of size 14 is 127.
0 4 6 20 35 52 59 77 78 86 89 99 122 127
Calculated in 87.747s

Length of ruler of size 15 is 151.
0 6 7 15 28 40 51 75 89 92 94 121 131 147 151
Calculated in 1941.028s
*/

bool recurse(State used, State left) {
    if (cnt == n)
        return true;
    if (cnt * 2 <= n && nums[cnt - 1] * 2 > m)
        return false;

    if (ans[n - cnt] > m - nums[cnt - 1])
        return false;

    int next = 0, sum = 0;
    for (int i = cnt; i < n; i++) {
        next++;
        while (used.get(next))
            next++;
        sum += next;
        if (nums[cnt - 1] + sum > m) return false;
    }
    sum -= next;
    
    left.set(0);
    for (int cand = nums[cnt - 1] + 1; cand + sum <= m; cand++) {
        /*
        // Aggressive heuristics to process N = 15.
        // Not necessary for actual task, where N <= 14.
        if (cnt == 1 && cand > n / 2) break;
        if (cnt == 2 && cand > n) break;
        if (cnt == 3 && cand > n * 2) break;
        */

        left.shift();
        if (!used.intersect(left)) {
            used.change(left);
            nums[cnt++] = cand;
            if (recurse(used, left))
                return true;
            cnt--;
            used.change(left);
        }
    }
    return false;
}

void findRuler(int size) {
    unsigned sTime = clock();

    vector <int> seq;
    int upper = min(MAX - 1, (ans[size - 1] + 1) * 2);
    for (int len = upper; len > ans[size - 1]; len--) {
        unsigned cTime = clock();
        fprintf(stderr, "  >> trying len %d... ", len);
        n = size, m = len;
        
        cnt = 0;
        nums[cnt++] = 0;
        bool hasAnswer = recurse(State(), State());
        fprintf(stderr, " (%.3lfs)\n", (double)(clock() - cTime) / CLOCKS_PER_SEC);
        if (hasAnswer) {
            seq = vector <int>(nums, nums + n);
            len = nums[n - 1];
        } else break;
    }
    
    ans[size] = seq.back();
    fprintf(stderr, "Length of ruler of size %d is %d.\n", (int)seq.size(), (int)seq.back());
    for (int i = 0; i < (int)seq.size(); i++)
        fprintf(stderr, "%d%c", seq[i], i + 1 == (int)seq.size() ? '\n' : ' ');
    fprintf(stderr, "Calculated in %.3lfs\n", (double)(clock() - sTime) / CLOCKS_PER_SEC);
}

void findRulers() {
    ans[0] = ans[1] = 0;
    for (int size = 2; size <= 15; size++) {
        findRuler(size);
    }
}

int main(void) {
    // in = fopen("Ruler.in", "rt");

    // findRulers();

    vector < vector <int> > answers = {
        {0},
        {0},
        {0, 1},
        {0, 1, 3},
        {0, 1, 4, 6},
        {0, 1, 4, 9, 11},
        {0, 1, 4, 10, 12, 17},
        {0, 1, 4, 10, 18, 23, 25},
        {0, 1, 4, 9, 15, 22, 32, 34},
        {0, 1, 5, 12, 25, 27, 35, 41, 44},
        {0, 1, 6, 10, 23, 26, 34, 41, 53, 55},
        {0, 1, 4, 13, 28, 33, 47, 54, 64, 70, 72},
        {0, 2, 6, 24, 29, 40, 43, 55, 68, 75, 76, 85},
        {0, 2, 5, 25, 37, 43, 59, 70, 85, 89, 98, 99, 106},
        {0, 4, 6, 20, 35, 52, 59, 77, 78, 86, 89, 99, 122, 127},
        {0, 6, 7, 15, 28, 40, 51, 75, 89, 92, 94, 121, 131, 147, 151}
    };

    fscanf(in, "%d", &n);
    for (int i = 0; i < n; i++)
        fprintf(out, "%d%c", answers[n][i], i + 1 == n ? '\n' : ' ');
    
    return 0;
}

/*
ID: espr1t
TASK: Ruler
KEYWORDS: Hard, Bruteforce, Golomb Ruler
*/

#include <cstdio>
#include <vector>
#include <algorithm>

using namespace std;
FILE* in = stdin; FILE* out = stdout;

const int STATE_SIZE = 4;

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
        mask[idx >> 6] |= (1ull << (idx & 63));
    }

    bool get(int idx) {
        return !!(mask[idx >> 6] & (1ull << (idx & 63)));
    }

    void clear() {
        for (int i = 0; i < STATE_SIZE; i++)
            mask[i] = 0;
    }
};

int n, m;
int nums[STATE_SIZE * 64], cnt;
State state;

bool recurse(State cur) {
    if (cnt == n)
        return true;

    int next = 0, sum = 0;
    for (int i = cnt; i < n; i++) {
        next++;
        while (state.get(next))
            next++;
        sum += next;
        if (nums[cnt - 1] + sum > m) return false;
    }
    sum -= next;

    cur.set(0);
    for (int cand = nums[cnt - 1] + 1; cand + sum <= m; cand++) {
        for (int i = STATE_SIZE - 1; i >= 0; i--) {
            if (i + 1 < STATE_SIZE)
                cur.mask[i + 1] |= !!(cur.mask[i] & (1ull << 63));
            cur.mask[i] <<= 1;
        }

        bool okay = true;
        for (int i = 0; i < STATE_SIZE; i++) {
            okay &= !(state.mask[i] & cur.mask[i]);
            if (!okay) break;
        }
        if (okay) {
            for (int i = 0; i < STATE_SIZE; i++)
                state.mask[i] ^= cur.mask[i];
            nums[cnt++] = cand;
            if (recurse(cur)) {
                return true;
            }
            cnt--;
            for (int i = 0; i < STATE_SIZE; i++)
                state.mask[i] ^= cur.mask[i];
        }
    }
    return false;
}

void findRuler(int size) {
    vector <int> seq;
    for (int len = STATE_SIZE * 64 - 1; ; len--) {
        n = size, m = len;
        cnt = 0;
        nums[cnt++] = 0;
        state.clear();
        if (!recurse(State()))
            break;
        seq = vector <int>(nums, nums + n);
        len = nums[n - 1];
    }

    sort(seq.begin(), seq.end());
    for (int i = 0; i < (int)seq.size(); i++)
        fprintf(out, "%d%c", seq[i], i + 1 == (int)seq.size() ? '\n' : ' ');
}

int main(void) {
    // in = fopen("Ruler.in", "rt");

    fscanf(in, "%d", &n);
    findRuler(n);
    
    return 0;
}

/*
ID: espr1t
TASK: ImageScanner
KEYWORDS: NP, HackConf 2019, Skyscanner
*/

#include <cstdio>
#include <cassert>
#include <vector>

using namespace std;
FILE* in = stdin; FILE* out = stdout;

const int MAX = 512;

int n, m, k;
int s[MAX][MAX][3];
int ans[MAX][MAX][3];

int usedQueries;
vector <int> query(int r1, int c1, int r2, int c2) {
    usedQueries++;
    assert(0 <= r1 && r1 <= r2 && r2 <= n - 1);
    assert(0 <= c1 && c1 <= c2 && c2 <= m - 1);
    
    vector <int> queryRes = {0, 0, 0};
    fprintf(out, "%d %d %d %d\n", r1, c1, r2, c2);
    fflush(out);
    fscanf(in, "%d %d %d", &queryRes[0], &queryRes[1], &queryRes[2]);
    return queryRes;
}

void update(int r1, int c1, int r2, int c2, const vector <int>& color, int atQuery = -1) {
    for (int row = r1; row <= r2; row++) {
        for (int col = c1; col <= c2; col++) {
            for (int i = 0; i < 3; i++) {
                ans[row][col][i] = color[i];
            }
        }
    }
}

int colorDist(const vector<int>& col1, const vector<int>& col2) {
    return (col1[0] - col2[0]) * (col1[0] - col2[0]) +
           (col1[1] - col2[1]) * (col1[1] - col2[1]) +
           (col1[2] - col2[2]) * (col1[2] - col2[2]);
}

long long calcDiff(int r1, int c1, int r2, int c2, const vector <int>& color) {
    long long diff = 0;
    for (int row = r1; row <= r2; row++) {
        for (int col = c1; col <= c2; col++) {
            diff += colorDist(color, {ans[row][col][0], ans[row][col][1], ans[row][col][2]});
        }
    }
    return diff;
}

void useSquares() {
    fscanf(in, "%d %d %d", &n, &m, &k);

    // Init answer with mid-gray
    usedQueries = 0;
    update(0, 0, n - 1, m - 1, {127, 127, 127});

    int size = 1;
    while (true) {
        int need = (n / size + !!(n % size)) *
                   (m / size + !!(m % size));
        if (need <= k) break;
        size++;
    }

    for (int row = 0; row < n; row += size) {
        int need = ((n - row) / (size - 1) + !!((n - row) % (size - 1))) *
                   (m / (size - 1) + !!(m % (size - 1)));
        if (need <= k - usedQueries) size--;

        for (int col = 0; col < m; col += size) {
            int erow = min(n - 1, row + size - 1);
            int ecol = min(m - 1, col + size - 1);
            vector <int> color = query(row, col, erow, ecol);
            update(row, col, erow, ecol, color);
        }
    }
    fprintf(stderr, "Used %d out of %d queries.\n", usedQueries, k);
}


int blur[MAX][MAX][3];
void useBlur() {
    int dir[8][2] = { {-1, -1}, {-1, 0}, {-1, 1}, {0, 1}, {1, 1}, {1, 0}, {1, -1}, {0, -1} };
    for (int iter = 0; iter < 3; iter++) {
        for (int row = 0; row < n; row++) {
            for (int col = 0; col < m; col++) {
                for (int i = 0; i < 3; i++) {
                    int sum = 0, neighbors = 0;
                    for (int c = 0; c < 8; c++) {
                        int nrow = row + dir[c][0]; if (nrow < 0 || nrow >= n) continue;
                        int ncol = col + dir[c][1]; if (ncol < 0 || ncol >= m) continue;
                        sum += ans[nrow][ncol][i];
                        neighbors++;
                    }
                    sum += ans[row][col][i] * (16 - neighbors);
                    blur[row][col][i] = sum / 16;
                }
            }
        }
        for (int row = 0; row < n; row++) {
            for (int col = 0; col < m; col++) {
                for (int i = 0; i < 3; i++) {
                    ans[row][col][i] = blur[row][col][i];
                }
            }
        }
    }
}

int main(void) {
    useSquares();
    useBlur();

    fprintf(stdout, "Ready\n");
    for (int row = 0; row < n; row++) {
        for (int col = 0; col < m; col++) {
            fprintf(out, "%d %d %d ", ans[row][col][0], ans[row][col][1], ans[row][col][2]);
        }
        fprintf(out, "\n");
    }
    fflush(out);
    fprintf(stderr, "Used %d out of %d queries.\n", usedQueries, k);

    return 0;
}

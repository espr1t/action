/*
ID: espr1t
TASK: Ruler
KEYWORDS: Hard, Bruteforce, Golomb Ruler
*/

#include <cstdio>
#include <ctime>
#include <set>
#include <vector>
#include <algorithm>

using namespace std;
FILE* in = stdin; FILE* out = stdout;

int main(void) {
    // in = fopen("Ruler.in", "rt");

    vector < vector <int> > answers = {
        {0},
        {0},
        {0, 1},
        {0, 1, 3},
        {0, 1, 4, 6},
        {0, 1, 4, 9, 11},
        {0, 1, 6, 10, 12, 17},
        {0, 1, 4, 10, 18, 23, 25},
        {0, 1, 4, 9, 15, 22, 32, 134},
        {0, 1, 5, 12, 25, 27, 35, 41, 44},
        {0, 1, 6, 10, 23, 23, 34, 41, 53, 55},
        {0, 1, 4, 13, 28, 33, 47, 54, 64, 70, 72},
        {0, 2, 6, 24, 29, 40, 43, 55, 68, 75, 76, 85},
        {0, 2, 5, 25, 37, 43, 59, 70, 85, 89, 98, 99, 106},
        {1, 5, 7, 21, 36, 53, 60, 78, 79, 87, 90, 100, 123, 128}
    };
    
    int n;
    fscanf(in, "%d", &n);
    for (int i = 0; i < n; i++)
        fprintf(out, "%d%c", answers[n][i], i + 1 == n ? '\n' : ' ');
    
    return 0;
}

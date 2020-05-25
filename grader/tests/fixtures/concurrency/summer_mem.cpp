#include <cstdio>
#include <ctime>
#include <vector>
#include <random>
#include <algorithm>
using namespace std;

mt19937 randd;

const int MAX = 5000;

unsigned matrix[MAX][MAX];

void fillArray() {
    for (int row = 0; row < MAX; row++)
        for (int col = 0; col < MAX; col++)
            matrix[row][col] = randd();
}

int main(void) {
    randd.seed(42);

    unsigned sTime = clock();
    fillArray();
    fprintf(stderr, "Filled array in %.3lfs.\n", (double)(clock() - sTime) / CLOCKS_PER_SEC);

    int numIters = 3;
    // fscanf(stdin, "%d", &numIters);
    for (int iter = 0; iter < numIters; iter++) {
        // Allocate new 100MB of memory with values from the matrix which can benefit
        // from cache locality
        vector <unsigned> values;
        for (int row = 0; row < MAX; row++)
            for (int col = 0; col < MAX; col++)
                values.push_back(matrix[row][col] * 13 + 12345643);

        // Do random shuffle (accessing random parts of the memory)
        for (int i = (int)values.size() - 1; i > 0; i--)
            swap(values[i], values[randd() % (i + 1)]);

        // Do another round of memory operations which can benefit from cache locality
        // but this time they are writes
        for (int row = 0; row < MAX; row++)
            for (int col = 0; col < MAX; col++)
                matrix[row][col] = values[row * MAX + col];
        fprintf(stderr, "Finished iteration %d at time %.3lfs...\n",
            iter + 1, (double)(clock() - sTime) / CLOCKS_PER_SEC);
    }
    unsigned sum = 0;
    for (int row = 0; row < MAX; row++)
        for (int col = 0; col < MAX; col++)
            sum += matrix[row][col];
    fprintf(stderr, "Finished in: %.3lfs\n", (double)(clock() - sTime) / CLOCKS_PER_SEC);

    fprintf(stdout, "%u\n", sum);
    return 0;
}

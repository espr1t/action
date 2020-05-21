/*
ID: espr1t
TASK: Snakes
KEYWORDS: NP, Game, Shortest Path
*/

#include <cstdio>
#include <cstdlib>

FILE* in = stdin; FILE* out = stdout;

const int MAX = 22;

int numApples;
int numRows, numCols;
char board[MAX][MAX];

int main(void) {
    //in = fopen("Snakes.in", "rt");
    
    fscanf(in, "%d %d %d", &numRows, &numCols, &numApples);
    for (int row = 0; row < numRows; row++)
        fscanf(in, "%s", board[row]);

    fprintf(out, "\n");

    return 0;
}


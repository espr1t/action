/*
ID: espr1t
TASK: Snakes
KEYWORDS: NP, Game, Shortest Path
*/

#include <cstdio>
#include <cstdlib>
#include <queue>

using namespace std;
FILE* in = stdin; FILE* out = stdout;

const int MAX = 22;
const int INF = 1000000001;

int numApples;
int numRows, numCols;
char board[MAX][MAX];

int dist[MAX][MAX];
int dir[4][2] = { {-1, 0}, {0, 1}, {1, 0}, {0, -1} };
const char* dirNames[4] = {"Up", "Right", "Down", "Left"};

void bfs() {
    int startRow = -1, startCol = -1;
    for (int row = 0; row < numRows; row++) {
        for (int col = 0; col < numCols; col++)
            if (board[row][col] == '@')
                startRow = row, startCol = col;
    }
    if (startRow == -1 || startCol == -1)
        exit(-1);
    
    queue < pair <int, int> > q;
    for (int row = 0; row < numRows; row++)
        for (int col = 0; col < numCols; col++)
            dist[row][col] = INF - 1;
    q.push(make_pair(startRow, startCol));
    dist[startRow][startCol] = 0;
    
    while (!q.empty()) {
        int curRow = q.front().first;
        int curCol = q.front().second;
        q.pop();
        
        for (int d = 0; d < 4; d++) {
            int nxtRow = curRow + dir[d][0]; if (nxtRow < 0 || nxtRow >= numRows) continue;
            int nxtCol = curCol + dir[d][1]; if (nxtCol < 0 || nxtCol >= numCols) continue;
            if (board[nxtRow][nxtCol] == '.' && dist[curRow][curCol] + 1 < dist[nxtRow][nxtCol]) {
                dist[nxtRow][nxtCol] = dist[curRow][curCol] + 1;
                q.push(make_pair(nxtRow, nxtCol));
            }
        }
    }
}

void move() {
    int headRow = -1, headCol = -1;
    for (int row = 0; row < numRows; row++) {
        for (int col = 0; col < numCols; col++) {
            if (board[row][col] == 'A')
                headRow = row, headCol = col;
        }
    }
    if (headRow == -1 || headCol == -1)
        exit(-1);
    
    int bestDir = -1, bestDist = INF;
    for (int d = 0; d < 4; d++) {
        int row = headRow + dir[d][0]; if (row < 0 || row >= numRows) continue;
        int col = headCol + dir[d][1]; if (col < 0 || col >= numCols) continue;
        if (board[row][col] != '.' && board[row][col] != '@')
            continue;
        if (bestDist > dist[row][col]) {
            bestDist = dist[row][col];
            bestDir = d;
        }
    }
    fprintf(out, "%s\n", bestDir == -1 ? dirNames[0] : dirNames[bestDir]);
}

int main(void) {
    //in = fopen("Snakes.in", "rt");
    
    fscanf(in, "%d %d %d", &numRows, &numCols, &numApples);
    for (int row = 0; row < numRows; row++)
        fscanf(in, "%s", board[row]);
    
    bfs();
    move();
    
    return 0;
}


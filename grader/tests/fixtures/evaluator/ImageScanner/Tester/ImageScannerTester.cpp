/*
ID: espr1t
TASK: ImageScanner
KEYWORDS: Interactive Tester
INSTRUCTIONS:
    The interactive tester is provided the input data through stdin
    at the beginning of execution. After that its stdin pipe is linked to
    the contestant's solution stdout and gets its input from there.
    Additionally, the tester's stdout is linked to the contestant
    solution's stdin, thus each write from one becomes available for read
    from the other.
    
    The tester should print its result on the stderr. It should contain two lines:
    1) The score
    2) An info message (the string "OK" if everything is okay)
    
    Additionally, a game log can be printed to the file passed as first argument.
    If printed, it will be available to a front-end visualizer.
    
    NOTE: Don't forget to flush() after each print to stdout!
*/

#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <algorithm>
#include <vector>

using namespace std;
FILE* logFile;

const int MAX = 512;

void finalVerdict(const char* verdict, double score, const char* message) {
    fprintf(stderr, "%s\n", verdict);
    fprintf(stderr, "%lf\n", score);
    fprintf(stderr, "%s\n", message);
    exit(0);
}

int maxQueries;
int numRows, numCols;
int sums[MAX][MAX][3];
int image[MAX][MAX][3];
int result[MAX][MAX][3];

void precalcSums() {
    for (int row = 0; row < numRows; row++) {
        for (int col = 0; col < numCols; col++) {
            for (int i = 0; i < 3; i++) {
                sums[row][col][i] = image[row][col][i];
                if (row > 0) sums[row][col][i] += sums[row - 1][col][i];
                if (col > 0) sums[row][col][i] += sums[row][col - 1][i];
                if (row > 0 && col > 0) sums[row][col][i] -= sums[row - 1][col - 1][i];
            }
        }
    }
}

char buff[200000];

void interact() {
    precalcSums();
    
    vector < vector <int> > queries;

    fprintf(stdout, "%d %d %d\n", numRows, numCols, maxQueries);
    fflush(stdout);
    
    int remQueries = maxQueries;
    while (true) {
        fscanf(stdin, "%s", buff);
        if (strlen(buff) > 5) {
            finalVerdict("WA", 0.0, "Unexpectedly long token.");
        }
        if (!strcmp(buff, "Ready"))
            break;
        
        if (--remQueries < 0) {
            finalVerdict("WA", 0.0, "Used more than the allowed number of queries.");
        }

        int row1, col1, row2, col2;
        if (sscanf(buff, "%d", &row1) != 1 || row1 < 0 || row1 >= numRows) {
            finalVerdict("WA", 0.0, "Invalid or missing token for row1.");
        }
        if (fscanf(stdin, "%d", &col1) != 1 || col1 < 0 || col1 >= numCols) {
            finalVerdict("WA", 0.0, "Invalid or missing token for col1.");
        }
        if (fscanf(stdin, "%d", &row2) != 1 || row2 < 0 || row2 >= numRows) {
            finalVerdict("WA", 0.0, "Invalid or missing token for row2.");
        }
        if (fscanf(stdin, "%d", &col2) != 1 || col2 < 0 || col2 >= numCols) {
            finalVerdict("WA", 0.0, "Invalid or missing token for col2.");
        }
        
        if (row1 > row2 || col1 > col2) {
            finalVerdict("WA", 0.0, "Invalid values: row1 > row2 or col1 > col2.");
        }
        
        int res[3] = {0, 0, 0};
        for (int i = 0; i < 3; i++) {
            res[i] = sums[row2][col2][i];
            if (row1 > 0) res[i] -= sums[row1 - 1][col2][i];
            if (col1 > 0) res[i] -= sums[row2][col1 - 1][i];
            if (row1 > 0 && col1 > 0) res[i] += sums[row1 - 1][col1 - 1][i];
            res[i] /= (row2 - row1 + 1) * (col2 - col1 + 1);
        }
        fprintf(stdout, "%d %d %d\n", res[0], res[1], res[2]);
        fflush(stdout);
        queries.push_back({row1, col1, row2, col2, res[0], res[1], res[2]});
    }
    
    long long score = 0;
    for (int row = 0; row < numRows; row++) {
        for (int col = 0; col < numCols; col++) {
            for (int i = 0; i < 3; i++) {
                int value;
                if (fscanf(stdin, "%d", &value) != 1 || value < 0 || value > 255) {
                    finalVerdict("WA", 0.0, "Invalid or missing image pixel value.");
                }
                score += (value - image[row][col][i]) * (value - image[row][col][i]);
                result[row][col][i] = value;
            }
        }
    }

    // Print the log
    fprintf(logFile, "%d %d %d\n", numRows, numCols, maxQueries);

    fprintf(logFile, "%d\n", (int)queries.size());
    for (int i = 0; i < (int)queries.size(); i++) {
        // row1, col1, row2, col2, res[0], res[1], res[2]);
        for (int c = 0; c < (int)queries[i].size(); c++) {
            fprintf(logFile, "%d%c", queries[i][c], c + 1 == (int)queries[i].size() ? '\n' : ' ');
        }
    }
    
    for (int row = 0; row < numRows; row++) {
        for (int col = 0; col < numCols; col++) {
            fprintf(logFile, "%d %d %d%c", image[row][col][0], image[row][col][1],
                                           image[row][col][2], col + 1 == numCols ? '\n' : ' ');
        }
    }

    for (int row = 0; row < numRows; row++) {
        for (int col = 0; col < numCols; col++) {
            fprintf(logFile, "%d %d %d%c", result[row][col][0], result[row][col][1],
                                           result[row][col][2], col + 1 == numCols ? '\n' : ' ');
        }
    }

    char message[128];
    sprintf(message, "Got score %lld.", score);
    finalVerdict("OK", score, message);
}

int main(int argc, char** argv) {
    logFile = fopen(argv[1], "wt");
    
    // Read Input
    fscanf(stdin, "%d %d %d", &numRows, &numCols, &maxQueries);
    for (int row = 0; row < numRows; row++) {
        for (int col = 0; col < numCols; col++) {
            int value;
            fscanf(stdin, "%d", &value);
            image[row][col][0] = (value >> 16) & 255;
            image[row][col][1] = (value >>  8) & 255;
            image[row][col][2] = (value >>  0) & 255;
        }
    }
    
    // Calculate score
    interact();
    return 0;
}


# Testers
For tasks with two players, the authors must prepare a program which should evaluate contestant's
submissions: pass the correct input to each solution at each turn, read its output, validate it,
and pass the appropriate input to the other player's solution until the game finishes.

The system currently supports only C++ testers, which should work as follows.

## Input
The tester is provided a single argument:
1. The name of a logfile (used for replays).

The rest of the input comes from stdin and the output is printed to stdout. The problem's input is
initially passed to the tester on the stdin stream. After that, all contestant's output is provided
to the tester also through stdin.

The output of the tester (input to the contestant's programs) happens on stdout. A part of the
system takes care to pass this output as input to the correct solution whose turn it is. The tester
should not differentiate between the players and should always print the next player's output to stdout.

## Checker or System Error
In case there is a problem with the tester itself or the stystem (e.g., the tester crashes or the
logfile is missing or something else) the verdict is expected to be IE (Internal Error).

## Expected output
In case everything goes well (that is, the tester ran smoothly) print on the stderr stream three or
four lines:
1. Line 1: The verdict. Accepted answers expect verdict "OK" (everything was okay). Problems with
the tester or problem's input should be marked as "IE" (Internal Error).
1. Line 2: A float - the score of the first player (the solution, which was ran first and on odd turns)
1. Line 3: A float - the score of the second player (the solution, which was ran second and on even turns)
1. Line 4: Optional message (returned to the user).

You can print any scores, not necessarily the ones given in the example below.

## Example tester
```C++
/*
TASK: Taskname
KEYWORDS: Tester
*/

#include <cstdio>
#include <cstring>
#include <string>

using namespace std;
FILE* in = stdin; FILE* out = stdout; FILE* replay;

const int MAX_BUFF_SIZE = 20000000;
char buff[MAX_BUFF_SIZE];

const double SCORE_WIN = 3.0;
const double SCORE_DRAW = 1.0;
const double SCORE_LOSS = 0.0;

int curPlayer = 0;

void finalVerdict(const char* verdict, double score1, double score2, const char* message) {
    fprintf(stderr, "%s\n", verdict);
    fprintf(stderr, "%.2lf\n", score1);
    fprintf(stderr, "%.2lf\n", score2);
    fprintf(stderr, "%s\n", message);
    exit(0);
}

void printInput() {
    // Print some input to one of the players to stdout
    ...
    // Don't forget to flush
    fflush(out);
}

void validate(const string str) {
    return false;
}

void gameCycle() {
    // Print the input for the current player
    printInput();

    // Read output from one of the players
    string output;
    buff[0] = 0;
    fgets(buff, MAX_BUFF_SIZE, in);
    output += buff;

    // Validate it
    if (!validate(output)) {
        char message[1024];
        sprintf(message, "%s player printed invalid move!", !curPlayer ? "First" : "Second");
        finalVerdict("OK", !curPlayer ? SCORE_LOSS : SCORE_WIN, curPlayer ? SCORE_LOSS : SCORE_WIN, message);
    }
    
    // Do the rest of the move evaluation (game logic)
    string status = "none";
    ...

    // Print the replay log
    fprintf(replay, "%d%d%d%d\n", largeRow, largeCol, smallRow, smallCol);
    fflush(replay);

    // Drawn?
    if (status == "draw") {
        finalVerdict("OK", SCORE_DRAW, SCORE_DRAW, "The match ended in a draw.");
    }
    
    // Winning?
    if (status == "win") {
        char message[1024];
        sprintf(message, "%s player won.\n", !curPlayer ? "First" : "Second");
        finalVerdict("OK", curPlayer ? SCORE_LOSS : SCORE_WIN, !curPlayer ? SCORE_LOSS : SCORE_WIN, message);
    }

    // Update the state for the next player
    ...

    // Swap the players (first player becomes second and vice-versa)
    curPlayer = !curPlayer;
}

int main(int argc, char** argv) {
    replay = fopen(argv[1], "wt");

    // Read the input of the task
    fgets(buff, MAX_BUFF_SIZE, in);
    ...

    // Run the game cycle swapping the player after each iteration
    while (true) {
        gameCycle();
    }
    return 0;
}

```

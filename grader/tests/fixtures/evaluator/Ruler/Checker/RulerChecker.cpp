/*
ID: espr1t
TASK: Ruler
KEYWODS: Checker
*/

#include <cstdio>
#include <cstdlib>
#include <vector>
#include <algorithm>
#include <set>

using namespace std;

int main(int argc, char** argv) {
    if (argc < 4) {
        fprintf(stderr, "Expected 3 arguments: "
            "input_file contestant_output author_output\n");
        return -1;
    }
    
    FILE *inp, *out, *sol;
    if (!(inp = fopen(argv[1], "rt"))) {
        fprintf(stderr, "Could not open file %s.\n", argv[1]);
        return -1;
    }
    if (!(out = fopen(argv[2], "rt"))) {
        fprintf(stderr, "Could not open file %s.\n", argv[2]);
        return -1;
    }
    if (!(sol = fopen(argv[3], "rt"))) {
        fprintf(stderr, "Could not open file %s.\n", argv[3]);
        return -1;
    }
    
    int N;
    if (fscanf(inp, "%d", &N) != 1) {
        fprintf(stderr, "INTERNAL ERROR: Could not read N from input file!\n");
        return -1;
    }
    
    vector <int> author;
    for (int i = 0; i < N; i++) {
        int num;
        if (fscanf(sol, "%d", &num) != 1) {
            fprintf(stderr, "INTERNAL ERROR: Could not read N integers from author's output!\n");
            return -1;
        }
        author.push_back(num);
    }
    sort(author.begin(), author.end());
    
    vector <int> contestant;
    for (int i = 0; i < N; i++) {
        int num;
        if (fscanf(out, "%d", &num) != 1) {
            fprintf(stdout, "WA\n");
            fprintf(stdout, "0\n");
            fprintf(stdout, "Could not read N integers from contestant's output!\n");
            return 0;
        }
        contestant.push_back(num);
    }
    sort(contestant.begin(), contestant.end());
    
    if (contestant.front() != 0) {
            fprintf(stdout, "WA\n");
            fprintf(stdout, "0\n");
            fprintf(stdout, "Contestant's output does not begin with a zero!\n");
            return 0;
    }

    if (contestant.back() != author.back()) {
            fprintf(stdout, "WA\n");
            fprintf(stdout, "0\n");
            fprintf(stdout, "Contestant's ruler length is different than expected!\n");
            return 0;
    }
    
    for (int i = 1; i < (int)contestant.size(); i++) {
        if (contestant[i] == contestant[i - 1]) {
            fprintf(stdout, "WA\n");
            fprintf(stdout, "0\n");
            fprintf(stdout, "Contestant's output contains duplicate elements!\n");
            return 0;
        }
    }
    
    set <int> seen;
    for (int i = 0; i < (int)contestant.size(); i++) {
        for (int c = i + 1; c < (int)contestant.size(); c++) {
            if (seen.find(contestant[c] - contestant[i]) != seen.end()) {
                fprintf(stdout, "WA\n");
                fprintf(stdout, "0\n");
                fprintf(stdout, "Contestant's output contains duplicate distance between elements!\n");
                return 0;
            }
            seen.insert(contestant[c] - contestant[i]);
        }
    }
    
    fprintf(stdout, "OK\n");
    fprintf(stdout, "1.0\n");
    return 0;
}

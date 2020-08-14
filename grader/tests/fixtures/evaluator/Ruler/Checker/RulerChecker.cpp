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

void finalVerdict(const char* verdict, double score, const char* message = "") {
    fprintf(stderr, "%s\n", verdict);
    fprintf(stderr, "%lf\n", score);
    fprintf(stderr, "%s\n", message);
    exit(0);
}

int main(int argc, char** argv) {
    if (argc < 4) {
        finalVerdict("IE", 0.0, "Expected 3 arguments: input_file solution_output author_output.");
    }
    
    FILE *inp, *out, *sol;
    if (!(inp = fopen(argv[1], "rt"))) {
        finalVerdict("IE", 0.0, "Could not open input file!");
    }
    if (!(out = fopen(argv[2], "rt"))) {
        finalVerdict("IE", 0.0, "Could not open contestant's output file!");
    }
    if (!(sol = fopen(argv[3], "rt"))) {
        finalVerdict("IE", 0.0, "Could not open author's output file!");
    }
    
    int N;
    if (fscanf(inp, "%d", &N) != 1) {
        finalVerdict("IE", 0.0, "Could not read N from input file!");
    }
    
    vector <int> author;
    for (int i = 0; i < N; i++) {
        int num;
        if (fscanf(sol, "%d", &num) != 1) {
            finalVerdict("IE", 0.0, "Could not read N integers from author's output!");
        }
        author.push_back(num);
    }
    sort(author.begin(), author.end());
    
    vector <int> contestant;
    for (int i = 0; i < N; i++) {
        int num;
        if (fscanf(out, "%d", &num) != 1) {
            finalVerdict("WA", 0.0, "Could not read N integers from contestant's output!");
        }
        contestant.push_back(num);
    }
    sort(contestant.begin(), contestant.end());
    
    if (contestant.front() != 0) {
        finalVerdict("WA", 0.0, "Contestant's output does not begin with a zero!");
    }

    if (contestant.back() != author.back()) {
        finalVerdict("WA", 0.0, "Contestant's ruler length is different than expected!");
    }
    
    for (int i = 1; i < (int)contestant.size(); i++) {
        if (contestant[i] == contestant[i - 1]) {
            finalVerdict("WA", 0.0, "Contestant's output contains duplicate elements!");
        }
    }
    
    set <int> seen;
    for (int i = 0; i < (int)contestant.size(); i++) {
        for (int c = i + 1; c < (int)contestant.size(); c++) {
            if (seen.find(contestant[c] - contestant[i]) != seen.end()) {
                finalVerdict("WA", 0.0, "Contestant's output contains duplicate distance between elements!");
            }
            seen.insert(contestant[c] - contestant[i]);
        }
    }
    
    finalVerdict("OK", 1.0);
    return 0;
}

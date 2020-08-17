/*
ID: espr1t
TASK: ABProblem
KEYWORDS: Trivial, Implementation
*/

#include <cstdio>
#include <cstdlib>

FILE* in = stdin; FILE* out = stdout;

int main(void) {
    double a, b;
    fscanf(in, "%lf %lf", &a, &b);
    
    char command[1024];
    sprintf(command, "echo '%.12lf * %.12lf' | bc -l", a, b);
    system(command);
    return 0;
}

/*
ID: espr1t
TASK: ABProblem
KEYWORDS: Trivial, Implementation
*/

#include <cstdio>
#include <vector>
#include <string>

using namespace std;
FILE* in = stdin; FILE* out = stdout;

int main(void) {
    double a, b;
    fscanf(in, "%lf %lf", &a, &b);
    
    if (a < 0 || b < 0) {
        int targetSize = 3000; // Megabytes
        int stringSize = 1000000; // Bytes

        string str;
        for (int i = 0; i < stringSize; i++)
            str += 'A' + (i % 26);
        vector <string> v;
        for (int i = 0; i < targetSize; i++)
            v.push_back(str);
    }
    
    fprintf(out, "%.9lf\n", a * b);
    return 0;
}

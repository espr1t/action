/*
ID: espr1t
TASK: NumberGuessing
KEYWORDS: Easy, Binary Search
*/

#include <cstdio>
#include <cstring>

int main(void) {
    int left = 1, right = 1000;
    while (left <= right) {
        int mid = (left + right) / 2;
        if (mid >= 700 && mid <= 900) {
            while (true);
        }
        fprintf(stdout, "%d\n", mid);
        fflush(stdout);
        char response[32];
        fscanf(stdin, "%s", response);
        if (!strcmp(response, "Correct!")) {
            break;
        } else if (!strcmp(response, "Smaller.")) {
            left = mid + 1;
        } else if (!strcmp(response, "Larger.")) {
            right = mid - 1;
        } else {
            fprintf(stderr, "Invalid response: %s\n", response);
            return -1;
        }
    }
    return 0;
}

/*
ID: espr1t
LANG: C++
TASK: InputOutput
KEYWORDS: Trivial, Parsing
*/

#include <cstdio>
#include <cstring>

int main(void) {
	FILE* in = stdin; FILE* out = stdout;
//	in = fopen("InputOutput.in", "rt"); out = fopen("InputOutput.out", "wt");
	
	int numTests;
	fscanf(in, "%d", &numTests);
	for (int test = 0; test < numTests; test++) {
		char command[32];
		fscanf(in, "%s", command);
		if (!strcmp(command, "Array")) {
			int a[128], n;
			fscanf(in, "%d", &n);
			for (int i = 0; i < n; i++)
				fscanf(in, "%d", &a[i]);
			for (int i = n - 1; i >= 0; i--)
				fprintf(out, "%d%c", a[i], i == 0 ? '\n' : ' ');
		} else {
			char a[128];
			fscanf(in, "%s", a);
			for (int i = (int)strlen(a) - 1; i >= 0; i--)
				fprintf(out, "%c", a[i]);
			fprintf(out, "\n");
		}
	}
	return 0;
}

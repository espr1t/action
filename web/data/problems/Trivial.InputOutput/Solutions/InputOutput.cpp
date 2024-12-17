/*
ID: espr1t
LANG: C++
TASK: InputOutput
KEYWORDS: Trivial, Parsing
*/

#include <cstdio>
#include <cstring>

FILE* in = stdin; FILE* out = stdout;

int main(void) {
	// in = fopen("InputOutput.in", "rt");
	
	char command[8];
	fscanf(in, "%s", command);
	if (command[0] == 'A') {
		int a[101], n = 0;
		while (fscanf(in, "%d", &a[n]) == 1)
		    n++;
		for (int i = n - 1; i >= 0; i--)
			fprintf(out, "%d%c", a[i], i == 0 ? '\n' : ' ');
	} else {
		char a[101];
		fscanf(in, "%s", a);
		for (int i = (int)strlen(a) - 1; i >= 0; i--)
			fprintf(out, "%c", a[i]);
		fprintf(out, "\n");
	}
	return 0;
}

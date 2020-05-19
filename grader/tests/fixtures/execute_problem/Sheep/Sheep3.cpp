/*
TASK: Sheep
LANG: C++
AUTHOR: Ivan Metelsky
CONTEST: TopCoder MRM
*/

#include <cstdio>
#include <cstring>
#include <vector>
#include <algorithm>
#include <set>
#include <string>
#include <iostream>
#include <sstream>
#include <queue>
using namespace std;

FILE* in = stdin; FILE* out = stdout;

class Sheep {
	public:
		int stupidNumRuns(vector <int> weights, int boatSize, int maxRuns) {
			int N = weights.size();
			bool used[4096];
			memset(used, 0, sizeof(used));

			int sheepLeft = N;
			for (int run=0; run < maxRuns; run++) {
				int tot = 0;
				for (int i=0; i < N; i++) {
					if (tot + weights[i] <= boatSize && !used[i]) {
						tot += weights[i];
						used[i] = true;
						sheepLeft--;
					}
				}
				if (sheepLeft == 0) return run + 1;
			}
			return maxRuns + 1;
		}

		int minCapacity(int numSheep, int maxRuns, vector <int> weights) {
			sort(weights.rbegin(), weights.rend());

			int boatSize = -1000000000;
			for (int i = 0; i < (int)weights.size(); i++)
				boatSize = max(boatSize, weights[i]);

			int bound = 0;
			for (int i = 0; i < (int)weights.size(); i++)
				bound += weights[i];
			bound /= maxRuns;

			if (boatSize < bound) boatSize = bound;

			int cnt = ((int)weights.size() + maxRuns - 1) / maxRuns;
			bound = 0;
			for (int i=(int)weights.size() - cnt; i < (int)weights.size(); i++) bound += weights[i];
			if (boatSize < bound) boatSize = bound;

			while (stupidNumRuns(weights, boatSize, maxRuns) > maxRuns) boatSize++;
			return boatSize;
		}
};

int main(void) {
//	in = fopen("Sheep.in", "rt"); out = fopen("Sheep.out", "wt");

    int numSheep, maxRides;
    fscanf(in, "%d %d", &numSheep, &maxRides);
    vector <int> sheep;
    for (int i = 0; i < numSheep; i++) {
        int weight;
        fscanf(in, "%d", &weight);
        sheep.push_back(weight);
    }

    Sheep sh;
    fprintf(out, "%d\n", sh.minCapacity(numSheep, maxRides, sheep));

	return 0;
}

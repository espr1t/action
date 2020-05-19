/*
TASK: Sheep
LANG: C++
AUTHOR: Alexander Georgiev
CONTEST: TopCoder SRM 493
*/

#include <cstdio>
#include <cstring>
#include <vector>
#include <algorithm>
#include <queue>
#include <ctime>
using namespace std;

#define TREE 4096
FILE* in = stdin; FILE* out = stdout;

class Sheep {
	public:

		bool eval(vector <int>& sheep, int maxRuns, int capacity) {
		    vector <bool> used(sheep.size(), false);
		    int got = 0;
		    for (int i = 0; i < maxRuns; i++) {
		        int rem = capacity;
		        for (int c = 0; c < (int)sheep.size(); c++) {
		            if (!used[c] && rem >= sheep[c]) {
		                got++;
		                used[c] = true;
		                rem -= sheep[c];
                    }
                }
                if (got >= (int)sheep.size())
                    return true;
		    }
		    return false;
		}

		int minCapacity(int numSheep, int maxRuns, vector <int> sheep) {
            sort(sheep.rbegin(), sheep.rend());
			int ans = 4000000;
			int left = 0, right = 4000000;
			while (left <= right) {
			    int mid = (left + right) / 2;
			    if (eval(sheep, maxRuns, mid))
			        right = mid - 1, ans = mid;
			    else left = mid + 1;
			}
			return ans;
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

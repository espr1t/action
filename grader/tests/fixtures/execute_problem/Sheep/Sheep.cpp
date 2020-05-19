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
using namespace std;

#define TREE 4096
FILE* in = stdin; FILE* out = stdout;

int tree[TREE], cnt[TREE];
int saveTree[TREE], saveCnt[TREE];

void update(int val) {
	int idx = val + (TREE >> 1);
	cnt[idx]++;

	while (idx) {
	    tree[idx] = max(tree[idx], val);
	    idx >>= 1;
	}
}

void erase(int val) {
	int idx = val + (TREE >> 1);
	cnt[idx]--;

	if (!cnt[idx]) {
		tree[idx] = -1; idx >>= 1;
		while (idx) {
			tree[idx] = max(tree[idx << 1], tree[(idx << 1) + 1]);
			idx >>= 1;
		}
	}
}

int query(int idx) {
	idx += (TREE >> 1);
	int ans = tree[idx];
	int flag = (idx & 1); idx >>= 1;
	while (idx) {
		if (flag) ans = max(ans, tree[idx << 1]);
		flag = (idx & 1); idx >>= 1;
	}
	return ans;
}


class Sheep {
	public:

		bool eval(int cap, int n, int k) {
			memcpy(cnt, saveCnt, sizeof(cnt));
			memcpy(tree, saveTree, sizeof(tree));

			int left = n;
			for (int i = 0; i < k; i++) {
				int rem = cap;
				while (left) {
					int num = query(min(2001, rem));
					if (num == -1) break;
					rem -= num; erase(num); left--;
				}
				if (left == 0) return true;
			}
			return false;
		}

		int minCapacity(int numSheep, int maxRuns, vector <int> sheep) {
			memset(tree, -1, sizeof(tree));
			memset(cnt, 0, sizeof(cnt));

			int weightSum = 0;
			for (int i = 0; i < (int)sheep.size(); i++) {
				update(sheep[i]);
				weightSum += sheep[i];
			}
			memcpy(saveTree, tree, sizeof(saveTree));
			memcpy(saveCnt, cnt, sizeof(saveCnt));
			int initCap = weightSum / maxRuns;
			while (!eval(initCap, numSheep, maxRuns))
                initCap++;
			return initCap;
		}
};

int main(void) {
//	in = fopen("Sheep.in", "rt"); // out = fopen("Sheep.out", "wt");

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

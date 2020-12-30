#define _CRT_SECURE_NO_WARNINGS
#include <stdio.h>
#include <algorithm>
#include <math.h>
#include <string.h>
#include <vector>
#include <queue>
#include <functional>
#include <unordered_map>

const int maxn = 50000;
int n, m, k;
std::vector<std::pair<int, int>> graph[maxn];
int edgeWeights[200000];
std::vector<int> ans;

// Used for the articulation point algorithm (Tarjans algorithm)
int parent[maxn];
int discoveryTime[maxn];
int lowValue[maxn];
bool ap[maxn];

// Return the number of nodes visited. Used to check connectivity.
int deepDarkFunction(int node, int& time, const int snow) {
	int result = 1;
	discoveryTime[node] = lowValue[node] = ++time;

	int children = 0;
	for (int i = 0, iEnd = graph[node].size(); i < iEnd; ++i) {
		const int nextNode = graph[node][i].first;
		const int weight = graph[node][i].second;
		if (weight > snow) {
			if (discoveryTime[nextNode] == 0) {
				parent[nextNode] = node;
				++children;
				result += deepDarkFunction(nextNode, time, snow);

				// Check if the next node has a back-edge to the root and
				// save if for this node as well
				if (lowValue[node] > lowValue[nextNode])
					lowValue[node] = lowValue[nextNode];

				// If the next node does not have a back-edge, then removing
				// this node will separate the next node. So this node is an
				// articulation point
				if (lowValue[nextNode] >= discoveryTime[node] && node != 0)
					ap[node] = true;
			} else {
				// Check if the edge is a back-edge
				if (lowValue[node] > discoveryTime[nextNode] && parent[node] != nextNode)
					lowValue[node] = discoveryTime[nextNode];
			}
		}
	}

	if (node == 0 && children > 1)
		ap[node] = true;

	return result;
}

int main() {
	scanf("%d%d%d", &n, &m, &k);

	if (k > n - 2) {
		printf("-1 0\n");
		return 0;
	}

	int x, y, z;
	for (int i = 0; i < m; ++i) {
		scanf("%d%d%d", &x, &y, &z);
		// Remove loops
		if (x == y) {
			--m;
			--i;
			continue;
		}
		graph[x - 1].push_back({ y - 1, z });
		graph[y - 1].push_back({ x - 1, z });
		edgeWeights[i] = z;
	}

	std::sort(edgeWeights, edgeWeights + m);

	int left = 0;
	int right = m - 1; // At least n-1 edges to be connected.
	int best = -1;
	int mid;
	while (left <= right) {
		mid = (left + right) / 2;
		// isConnected fill in temp with the articulation points
		int time = 0;
		memset(ap, 0, sizeof(ap));
		memset(parent, -1, sizeof(parent));
		memset(discoveryTime, 0, sizeof(discoveryTime));
		memset(lowValue, 0, sizeof(lowValue));
		if (deepDarkFunction(0, time, edgeWeights[mid]) == n) {
			std::vector<int> temp;
			for (int i = 0; i < n; ++i) {
				if (ap[i]) {
					temp.push_back(i + 1);
				}
			}
			if (temp.size() >= k) {
				best = edgeWeights[mid];
				if (ans.size() == temp.size()) {
					// Check for lexicographically smaller answer
					int res = 0;
					for (int i = 0, iEnd = ans.size(); i < iEnd; ++i) {
						if (ans[i] != temp[i]) {
							res = ans[i] - temp[i];
							break;
						}
					}
					if (res < 0) {
						// Ans is already smaller. Swap it so it swaps back.
						ans.swap(temp);
					}
				}
				ans.swap(temp);
				right = mid - 1;
			} else {
				left = mid + 1;
			}
		} else {
			right = mid - 1;
		}
	}

	printf("%d %d\n", best, (int)ans.size());
	for (int i = 0, iEnd = ans.size() - 1; i < iEnd; ++i) {
		printf("%d ", ans[i]);
	}
	if (!ans.empty()) {
		printf("%d\n", ans.back());
	}
}

/* Solution description
Sort the edge weight and binary search through them. For each fixed weight, check
every node if it is "dangerous" (called Articulation point).

**Articulation point algorithm**
**Find all articulation points in linear time O(n+m)**
First, I do a DFS from node 1 and create the walk tree. Using DFS instead of BFS
ensures that if node 1 has more than one child in the tree, then it is an AP.
The leaves are never APs (since they are the last nodes in a walk, so
removing them will not cut off any other node), but this is just a property, not
part of the algorithm.

Keep track of the discovery time of each node, and a "low" value. This value
means the lowest discovery time that can be reached from the node, not using the
edges from the tree (aka. only back edges). If the low value of a node is lower
than the discovery time of the discovery time, there is some path back to the
root, so it's not an AP. Otherwise, all paths lead to nodes in the same subtree,
hence this node is an AP.
*/

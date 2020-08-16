#include <algorithm>
#include <vector>
#include <cstring>
#include <string>
#include <cstdio>
#include <queue>
#include <stack>
#include <utility>
#include <ctime>

using namespace std;

const int MAXN = 11;
char board[MAXN + 1][MAXN + 1];
int R, C;
const int DIM = 9;
/// 3 rows, 3 cols, 1 main diag, and 1 sec diag -> 8
const int WAYS = 8;
int small[DIM][WAYS];
int smallWin[DIM];
int big[WAYS];
int bigWin;
const int secdiag[DIM] = { 2, 6, 10, 6, 10, 14, 10, 14, 18 };
const int diag[DIM] = { 0, -4, -8, 4, 0, -4, 8, 4, 0 };
vector<pair<int, int>> freePos[DIM];
int freeStart[DIM];


bool checkSmallWin(int idx) {
	if (smallWin[idx]) {
		return true;
	}
	for (int i = 0; i < WAYS; i++) {
		if (small[idx][i] == 3 || small[idx][i] == -3) {
			smallWin[idx] = small[idx][i] / 3;
			return true;
		}
	}
	return false;
}

bool checkBigWin() {
	if (bigWin) {
		return true;
	}
	for (int i = 0; i < WAYS; i++) {
		if (big[i] == 3 || big[i] == -3) {
			bigWin = big[i] / 3;
			return true;
		}
	}
	return false;
}
int getIdx(int i, int j) {
	return (i / 4) * 3 + j / 4;
}

int getNext(int i, int j) {
	return (i % 4) * 3 + j % 4;
}

pair<int, int> pos;
int idxFinal;

bool incr(int i, int j, bool player) {
	const int idx = getIdx(i, j);
	const int val = player ? 1 : -1;
	small[idx][i % 4] += val;
	small[idx][j % 4 + 3] += val;
	small[idx][6] += val*(diag[idx] == i - j);
	small[idx][7] += val*(secdiag[idx] == i + j);
	if (checkSmallWin(idx)) {
		big[idx / 3] += smallWin[idx];
		big[idx % 3 + 3] += smallWin[idx];
		big[6] += smallWin[idx] * (idx % 4 == 0);
		big[7] += smallWin[idx] * (idx == 2 || idx == 4 || idx == 6);
		return true;
	}
	return false;
}

void decr(int i, int j, bool player, bool b) {
	const int idx = getIdx(i, j);
	const int val = player ? 1 : -1;
	small[idx][i % 4] -= val;
	small[idx][j % 4 + 3] -= val;
	small[idx][6] -= val*(diag[idx] == i - j);
	small[idx][7] -= val*(secdiag[idx] == i + j);
	if (b) {
		big[idx / 3] -= smallWin[idx];
		big[idx % 3 + 3] -= smallWin[idx];
		big[6] -= smallWin[idx] * (idx % 4 == 0);
		big[7] -= smallWin[idx] * (idx == 2 || idx == 4 || idx == 6);
	}
}

void printBoard() {
	for (int i = 0; i < MAXN; i++) {
		printf("%s\n", board[i]);
	}
	printf("\n\n");
}

int eval() {
	int res = 0;
	//printBoard();
	for (int i = 0; i < DIM; i++) {
		if (checkSmallWin(i)) {
			res += smallWin[i] * 50;
		}
	}
	return res;
}



int giveIdx() {
	int idx = -1;
	if (checkSmallWin(5)) {
		for (int i = 0; i < DIM; i++) {
			if (!checkSmallWin(i)) {
				return i;
			}
		}
	}
	else {
		return 5;
	}
	return -1;
}

int freeposnum;
int maxdepth = 5;

int minimax(int idx, int depth, bool player) {
	if (checkBigWin()) {
		return bigWin * 10000;
	}
	if (depth > maxdepth) {
		return eval();
	}

	if (player) { /// Max -> 'X'
		if (idx < 0 || checkSmallWin(idx)) {
			idx = giveIdx();
			if (idx == -1) {
				return 0;
			}
		}
		int maxVal = -1e9;
		for (int i = 0; i < freePos[idx].size(); i++) {
			const auto p = freePos[idx][i];

			board[p.first][p.second] = 'X';
			auto it = freePos[idx].erase(freePos[idx].begin() + i);

			int saveSmall = smallWin[idx];
			int saveBig = bigWin;

			bool b = incr(p.first, p.second, player);
			int val = minimax(getNext(p.first, p.second), depth + 1, false);
			decr(p.first, p.second, player, b);

			if (saveSmall == 0 && smallWin[idx] != 0) {
				smallWin[idx] = 0;
			}
			if (saveBig == 0 && bigWin != 0) {
				bigWin = 0;
			}

			freePos[idx].insert(freePos[idx].begin() + i, p);
			board[p.first][p.second] = '.';

			if (val > maxVal) {
				maxVal = val;
				if (depth == 0) {
					pos = freePos[idx][i];
					idxFinal = idx;
				}
			}
		}
		return maxVal;
	}
	else { /// Min -> 'O'
		if (checkSmallWin(idx) || idx < 0) {
			idx = giveIdx();
			if (idx == -1) {
				return 0;
			}
		}
		int minVal = 1e9;
		for (int i = 0; i < freePos[idx].size(); i++) {
			const auto p = freePos[idx][i];

			board[p.first][p.second] = 'O';
			freePos[idx].erase(freePos[idx].begin() + i);

			int saveSmall = smallWin[idx];
			int saveBig = bigWin;

			bool b = incr(p.first, p.second, player);
			int val = minimax(getNext(p.first, p.second), depth + 1, true);
			decr(p.first, p.second, player, b);

			if (saveSmall == 0 && smallWin[idx] != 0) {
				smallWin[idx] = 0;
			}
			if (saveBig == 0 && bigWin != 0) {
				bigWin = 0;
			}

			freePos[idx].insert(freePos[idx].begin() + i, p);
			board[p.first][p.second] = '.';

			minVal = min(val, minVal);
		}
		return minVal;
	}
}
bool used[MAXN][MAXN];
void init() {
	for (int i = 0; i < DIM; i++) {
		freePos[i].reserve(DIM);
	}
	for (int i = 0; i < MAXN; i++) {
		for (int j = 0; j < MAXN; j++) {
			used[i][j] = board[i][j] != '.';
			if (board[i][j] == '.') {
				freePos[getIdx(i, j)].push_back(make_pair(i, j));
			}
			else if (board[i][j] == 'X' || board[i][j] == 'O') {
				const int idx = getIdx(i, j);
				const int val = board[i][j] == 'X' ? 1 : -1;
				small[idx][i % 4] -= val;
				small[idx][j % 4 + 3] -= val;
				small[idx][6] -= val*(diag[idx] == i - j);
				small[idx][7] -= val*(secdiag[idx] == i + j);
			}
		}
	}
	for (int i = 0; i < DIM; i++) {
		freeposnum += freePos[i].size();
		checkSmallWin(i);
	}
	if (freeposnum < 20) {
		maxdepth = 10;
	}
	checkBigWin();
}


int main() {
	scanf("%d %d", &R, &C);
	for (int i = 0; i < MAXN; i++) {
		scanf("%s", board[i]);
	}
	init();
	minimax(R * 3 + C, 0, true);
	if (R == -1) {
		R = idxFinal / 3;
		C = idxFinal % 3;
	}
	printf("%d %d %d %d\n", R, C, (pos.first % 4), (pos.second % 4));
	return 0;
}
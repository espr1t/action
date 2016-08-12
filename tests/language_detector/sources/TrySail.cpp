#include <iostream>
#include <sstream>
#include <algorithm>
#include <vector>
#include <string>
#include <queue>
#include <stack>
#include <map>
#include <set>
#include <cmath>
#include <cctype>
#include <cstdio>
#include <cstdlib>
#include <cstring>

/*using namespace std;
const int MAX = 52;
const int MM = 256;

int n;
int a[MAX];
int dyn[MAX][MM][MM];

int recurse(int idx, int t1, int t2, int t3) {
    if (idx >= n)
        return t1 + t2 + t3;
    if (dyn[idx][t1][t2] != -1)
        return dyn[idx][t1][t2];
    
    int ans = 0;
    ans = max(ans, recurse(idx + 1, t1 ^ a[idx], t2, t3));
    ans = max(ans, recurse(idx + 1, t1, t2 ^ a[idx], t3));
    ans = max(ans, recurse(idx + 1, t1, t2, t3 ^ a[idx]));
    return dyn[idx][t1][t2] = ans;
}

class TrySail {
    public:
    int get(vector <int> strength) {
        n = (int)strength.size();
        for (int i = 0; i < n; i++)
            a[i] = strength[i];
        memset(dyn, -1, sizeof(dyn));
        return recurse(0, 0, 0, 0);
    }
};
*/
#include <ctime>
#include <cmath>
#include <string>
#include <vector>
#include <sstream>
#include <iostream>
#include <algorithm>
using namespace std;

int main(int argc, char* argv[])
{
    int def = 4;
	if (argc == 1)
	{
		cout << "Testing TrySail (250.0 points)" << endl << endl; 
		for (int i = 0; i < 20; i++)
		{
			ostringstream s; s << argv[0] << " " << i;
			int exitCode = system(s.str().c_str());
			if (exitCode)
				cout << "#" << i << ": Runtime Error" << endl;
		}
		int T = time(NULL)-1468080279;
		double PT = T/60.0, TT = 75.0;
		cout.setf(ios::fixed,ios::floatfield);
		cout.precision(2);
		cout << endl;
		cout << "Time  : " << T/60 << " minutes " << T%60 << " secs" << endl;
		cout << "Score : " << 250.0*(.3+(.7*TT*TT)/(10.0*PT*PT+TT*TT)) << " points" << endl;
	}
	else
	{
		int _tc; istringstream(argv[1]) >> _tc;
		TrySail _obj;
		int _expected, _received;
		time_t _start = clock();
		switch (_tc)
		{
			case 0:
			{
				int strength[] = {2,3,3};
				_expected = 8;
				_received = _obj.get(vector <int>(strength, strength+sizeof(strength)/sizeof(int))); break;
			}
			case 1:
			{
				int strength[] = {7,3,5,2};
				_expected = 17;
				_received = _obj.get(vector <int>(strength, strength+sizeof(strength)/sizeof(int))); break;
			}
			case 2:
			{
				int strength[] = {13,13,13,13,13,13,13,13};
				_expected = 26;
				_received = _obj.get(vector <int>(strength, strength+sizeof(strength)/sizeof(int))); break;
			}
			case 3:
			{
				int strength[] = {114,51,4,191,9,81,0,89,3};
				_expected = 470;
				_received = _obj.get(vector <int>(strength, strength+sizeof(strength)/sizeof(int))); break;
			}
			case 4:
			{
				int strength[] = {108,66,45,82,163,30,83,244,200,216,241,249,89,128,36,28,250,190,70,95,117,196,19,160,255,129,10,109,189,24,22,25,134,144,110,15,235,205,186,103,116,191,119,183,45,217,156,44,97,197};
				_expected = 567;
				_received = _obj.get(vector <int>(strength, strength+sizeof(strength)/sizeof(int))); break;
			}
			case 5:
			{
				int strength[] = {90, 28, 8, 110, 204, 214, 33, 134, 52, 58, 209, 225, 112, 53, 212, 112, 18, 239, 195, 89, 226, 6, 223, 166, 55, 117, 130, 214, 220, 97, 164, 18, 3, 146, 188, 19, 254, 80, 98, 136, 84, 146, 128, 156, 172, 26, 228, 123, 7, 133};
				_expected = 684;
				_received = _obj.get(vector <int>(strength, strength+sizeof(strength)/sizeof(int))); break;
			}
			case 6:
			{
				int strength[] = {1, 2, 4, 8, 16, 32, 64, 128, 1, 2, 4, 8, 16, 32, 64, 128, 1, 2, 4, 8, 16, 32, 64, 128, 1, 2, 4, 8, 16, 32, 64, 128, 1, 2, 4, 8, 16, 32, 64, 128, 1, 2, 4, 8, 16, 32, 64, 128, 1, 2};
				_expected = 513;
				_received = _obj.get(vector <int>(strength, strength+sizeof(strength)/sizeof(int))); break;
			}
			case 7:
			{
				int strength[] = {1, 2, 4, 8, 16, 32, 64, 128, 1, 2, 4, 8, 16, 32, 64, 128, 1, 2, 4, 8, 16, 32, 64, 128, 1, 2, 4, 8, 16, 32, 64, 128, 1, 2, 4, 8, 16, 32, 64, 128};
				_expected = 765;
				_received = _obj.get(vector <int>(strength, strength+sizeof(strength)/sizeof(int))); break;
			}
			default: return 0;
		}
		cout.setf(ios::fixed,ios::floatfield);
		cout.precision(2);
		double _elapsed = (double)(clock()-_start)/CLOCKS_PER_SEC;
		if (_received == _expected)
			cout << "#" << _tc << ": Passed (" << _elapsed << " secs)" << endl;
		else
		{
			cout << "#" << _tc << ": Failed (" << _elapsed << " secs)" << endl;
			cout << "           Expected: " << _expected << endl;
			cout << "           Received: " << _received << endl;
		}
	}
}


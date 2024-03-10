#include <iostream>
#include <vector>
using namespace std;

int main(void) {
    int sizeInBytes;
    cin >> sizeInBytes;
    vector <int> a(sizeInBytes / 4);
    a[0] = 42;
    for (int i = 1; i < (int)a.size(); i++)
        a[i] = (a[i - 1] * 13 + 17) % 123456789;
    cout << (a[0] ^ a[(int)a.size() / 2] ^ a.back()) << endl;
    return 0;
}

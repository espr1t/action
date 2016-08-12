/*
ID: espr1t
LANG: C++
TASK: Demo
*/

#include <iostream>
#include <cstdio>
#include <cmath>
#include <vector>
#include <algorithm>
#include <cstdlib>
#include <string>
#include <queue>
#include <map>
#include <set>

#define MAX 1024

using namespace std;
FILE *in; FILE *out;

int isPalindrome(int num)
{
	vector <int> v;
	while (num) {v.push_back(num % 10); num /= 10;}
	
	for (int i=0; i<(int)v.size(); i++)
		if (v[i] != v[v.size() - i - 1]) return 0;
	return 1;
}

int isPrime(long long num)
{
	if (num == 0 || num == 1) return 0;

	if (num % 2 == 0) return (num == 2);
	long long lim = (int)sqrt(num);

	for (long long i=3; i<=lim; i+=2)
		if (num % i == 0) return 0;
	
	return 1;
}


int main(void)
{
	int i, c, j;
	int ans = 0, cur;
	
	in = stdin; out = stdout;
	
	for (i=1; i<1000; i++)
	{
		for (c=i; c<1000; c++)
		{
			for (j=c; j<1000; j++)
			{
				if (i + c + j == 1000)
				{
					if (i * i + c * c == j * j)
					{
						cout << i * c * j << endl;
						system("pause");
						return 0;
					}
				}
			}
		}
	}
	
	cout << ans << endl;
	system("pause");	
	return 0;
}

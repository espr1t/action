#include <iostream>
#include <cstdio>

using namespace std;

int n;
long long dp[100005];
long long mod=1000000007;

long long solve()
{

	for(int i=6;i<=n;i++)
	{
		dp[i]+=dp[i-1];
		dp[i]%=mod;
		dp[i]+=dp[i-3];
		dp[i]%=mod;
	}
}

void init()
{
	scanf("%d",&n);
	fill(dp,dp+n+1,0);
	dp[3]=1;
	dp[4]=1;
	dp[5]=1;
}

int main()
{
	init();
	solve();
	cout<<dp[n]<<endl;
}

#include <bits/stdc++.h>
using namespace std;
const int nmax=1e3+42;
int n,m;
set< pair<int,int> > adj[nmax];
int SZ[nmax];
stack<int> tour,emp;
void dfs(int node)
{
/*
cout<<"dfs: "<<node<<endl;
for(int i=1;i<=n;i++)
{
cout<<i<<" : ";
cout<<SZ[i]<<" - ";
for(auto k:adj[i])cout<<k.first<<" ";
cout<<endl;
}
system("pause");
*/
    while(SZ[node])
    {
    set< pair<int,int> >::iterator qqq=adj[node].begin();
    pair<int,int> k=*qqq;
	adj[node].erase(k);
	adj[k.first].erase({node,k.second});
	SZ[node]--;
	SZ[k.first]--;
	dfs(k.first);
	}
//cout<<"end dfs: "<<node<<endl;
tour.push(node);
}
set< pair<int,int> >fake;
int main ()
{
cin>>n>>m;
int a,b;
for(int i=1;i<=m;i++)
{
cin>>a>>b;
adj[a].insert({b,i});
adj[b].insert({a,i});
SZ[a]++;
SZ[b]++;
}
for(int i=1;i<=n;i++)
    if(SZ[i]%2==1)
        for(int j=i+1;j<=n;j++)
        if(SZ[j]%2==1)
        {
        fake.insert({i,j});
        fake.insert({j,i});
        SZ[i]++;
        SZ[j]++;
        m++;
        adj[i].insert({j,m});
        adj[j].insert({i,m});
        i=j;
        }
cout<<"Yes"<<endl;

for(int p=1;p<=n;p++)
{
dfs(p);
int t=tour.size();
if(t>1)
{
//cout<<"tour size "<<t<<" : ";
int prev=tour.top();
tour.pop();
t--;
while(t)
{
if(fake.count({prev,tour.top()})==0)
    cout<<prev<<" "<<tour.top()<<endl;
t--;
prev=tour.top();
tour.pop();
}
}
tour=emp;
}
return 0;
}
//copied edges will WA
/*
5 7
1 2
1 3
4 1
1 5
3 2
4 5
3 5

2 2
1 2
2 1

4 4
1 2
2 3
3 4
4 1

8 8
1 2
2 3
3 4
4 1
5 6
6 7
7 8
8 5
*/

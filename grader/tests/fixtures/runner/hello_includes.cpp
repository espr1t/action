#include<iostream>
#include<cstring>

#define endl '\n'

using namespace std;

const int MAXN=11;
char input[MAXN],res[MAXN];

int main(){
    ios::sync_with_stdio(false);
    cin.tie(nullptr);

    int n;
    cin>>n;

    int counter=0;
    for(int i=0;i<n;++i){
        cin>>input;
        if(strcmp(input,res)==0){
            ++counter;
        }else if(counter==0){
            strcpy(res,input);
            counter=1;
        }else{
            --counter;
        }
    }
    cerr<<res<<endl;
    cout<<"Hello, World!" << endl;
return 0;
}

/*
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <cmath>
#include <iostream>
#include <sstream>
#include <algorithm>
#include <vector>
#include <queue>
#include <stack>
#include <map>
#include <set>
#include <unordered_map>
#include <unordered_set>
#include <bits/stdc++.h>

using namespace std;

int main(void) {
    cout << "Hello, World!" << endl;
    return 0;
}
*/
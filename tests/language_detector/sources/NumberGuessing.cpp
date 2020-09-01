#include<iostream>
using namespace std;

int main()
{
    int p1 = 0 , p2 = 500 , p3 = 1000 ;
    int s = 0 ;
    while ( s == 0 )
        {
        cout << p2 << endl ;
        cout << flush ;
        string in ;
        cin >> in ;
        if ( in == "Smaller." )
            {
            int ad = 0 ;
            int p4 = p2 ;
            if ( (p3 - p2) % 2 > 0 ) ad = 1 ;
            p2 = p2 + ((p3 - p2) / 2 + ad) ;
            p1 = p4 ;
            }
        if ( in == "Larger." )
            {
            int ad = 0 ;
            int p4 = p2 ;
            if ( (p1 + p2) % 2 > 0 ) ad = 1 ;
            p2 = (p1 + p2) / 2 + ad ;
            p3 = p4 ;
            }
        if ( in == "Correct!" ) s = 1 ;
        }
    return 0 ;
}


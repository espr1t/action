# include <cstdio>

int main ()
{
    int n, cnt = 0, tol;
    char c;
    scanf ("%d\n", &n);
    while (n --)
    {
        scanf ("%c", &c);
        cnt += (c == '#');
        if (c == 'E')
            tol = cnt;
    }
    if (tol < (cnt - tol))
        printf ("%d\n", tol);
    else
        printf ("%d\n", cnt - tol);
    return 0;
}

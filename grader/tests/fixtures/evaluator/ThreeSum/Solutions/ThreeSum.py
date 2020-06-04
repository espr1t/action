n = int(input())
a = [0] * 200002
s = [0] * 200002
for i in range(n + 1):
    a[i] = i
for it in range(2, 4):
    s[n] = 0
    for i in range(n, 0, -1):
        s[i] = (s[i + 1] + a[i]) % 1000000007
        a[i] = (s[i] * i) % 1000000007
print((sum(a, 1) + 1000000006) % 1000000007)

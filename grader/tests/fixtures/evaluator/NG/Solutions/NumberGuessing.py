import sys

lower = 1
upper = 1000
while lower <= upper:
    mid = (lower + upper) // 2
    print(mid)
    sys.stdout.flush()
    ans = input()
    if ans == 'Smaller.':
        lower = mid + 1
    elif ans == 'Larger.':
        upper = mid - 1
    else:
        break

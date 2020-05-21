n = int(input())
target_size = n // 32  # Python 4-byte integers are slightly tiny bit larger

a = [42] * target_size
for i in range(1, target_size):
    a[i] = (a[i - 1] * 13 + 17) % 123456789
print(a[0] ^ a[target_size // 2] ^ a[target_size - 1])

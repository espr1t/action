foo = [42]
for i in range(100000):
    foo.append((foo[-1] * 17 + 69) % 426661337)
print(f"Hello, {foo[-1]}!")

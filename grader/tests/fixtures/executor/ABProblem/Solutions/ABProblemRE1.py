inp = input().split(" ")
a, b = float(inp[0]), float(inp[1])
if a < 0 or b < 0:
    with open("foo.txt", "wt") as out:
        out.write("{:.9f}\n".format(a * b))
print("{:.9f}\n".format(a * b))

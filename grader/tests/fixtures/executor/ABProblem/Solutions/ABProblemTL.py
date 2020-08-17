from time import sleep

inp = input().split(" ")
a, b = float(inp[0]), float(inp[1])
if a < 0 or b < 0:
    sleep(3.0)
print("{:.9f}\n".format(a * b))

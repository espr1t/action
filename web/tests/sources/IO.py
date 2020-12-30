import sys

input = ""

for x in xrange(0, 2):
    global input

    input = raw_input()

print "".join(list(input)[::-1])

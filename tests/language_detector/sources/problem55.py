s = ''' # this is a very \
        #long string if I had the \
        #energy to type more and more ...'''

def next(num):
    ret = 0
    cur = num

    while (cur):
        ret = ret * 10 + cur % 10
        cur = cur / 10;
    return num + ret;

def isPalindrome(num):
	strnum = str(num)
	strlen = len(strnum)
	
	for i in range(strlen / 2):
		if (strnum[i] != strnum[strlen - i - 1]): return 0
	
	return 1

ans = 0
for i in range(10000):
	num = i
	flag = 1
	for c in range(50):
		num = next(num)
		if isPalindrome(num):
			flag = 0
			break
	ans = ans + flag

print ans


def digsum(num):
	ans = 0
	while num:
		ans += num % 10
		num /= 10
	return ans

ans = 0
for i in range(100):
	for c in range(100):
		sum = digsum(pow(i, c))
		if ans < sum: ans = sum

print ans
		

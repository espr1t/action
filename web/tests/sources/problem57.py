'''import java.io.BufferedReader;
import java.io.File;
import java.io.FileNotFoundException;
import java.io.FileReader;
import java.io.IOException;
import java.util.Arrays;

public class EllysBounceGame {
    private long simulate(int[] tiles, int idx, int dir) {
        int at = idx;
        long ret = 0, last = 0;
        while (idx >= 0 && idx < tiles.length) {
            ret += tiles[idx];
            last += tiles[idx];
            if (tiles[idx] % 2 == 1) {
                ret += last++;
                ret -= tiles[idx]++;
                dir = -dir;
                int newAt = idx; idx = at; at = newAt;
            }
            idx += dir;
        }
        return ret;
    }
'''

def digcount(num):
	ans = 0
	while num:
		ans = ans + 1
		num /= 10
	return ans

n, ans = 1000, 0
num, den = 0, 1

for i in range(n):
	num, den = den, num + den * 2	
	if digcount(num + den) > digcount(den): ans = ans + 1

num += den
test = '*/'
print ans
		

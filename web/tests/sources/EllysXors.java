/*
ID: espr1t
LANG: Java
TASK: EllysXors
*/

public class EllysXors
{
	long xor1N(long N)
	{
		if (N % 4 == 0) return n;
		if (N % 4 == 1) return 1;
		if (N % 4 == 2) return n + 1;
		if (N % 4 == 3) return 0;
	}
	
	long getXor(long L, long R)
	{
		return fast(R) ^ fast(L - 1);
	}
	
	String checkData(long L, long R)
	{
		if (L < 1 || L > 4000000000L)
			return "L must be between 1 and 4,000,000,000, inclusive.";
		if (R < 1 || R > 4000000000L)
			return "R must be between 1 and 4,000,000,000, inclusive.";
		if (R < L)
			return "L must be less than or equal to R.";
		return "";
	}
};

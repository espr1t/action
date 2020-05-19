import java.util.Scanner;

public class ThreeSum {
    final static int MOD = 1000000007;

    public static void main(String[] args) {
        Scanner scan = new Scanner(System.in);
        int n = scan.nextInt();
        long[] a = new long[n + 2];
        long[] sum = new long[n + 2];

        for (int i = 1; i <= n; i++)
            a[i] = i;

        for (int iter = 2; iter <= 3; iter++) {
            sum[n] = 0;
            for (int i = n; i > 0; i--) {
                sum[i] = (sum[i + 1] + a[i]) % MOD;
                a[i] = (i * sum[i]) % MOD;
            }
        }
        long ans = 0;
        for (int i = 1; i <= n; i++)
            ans = (ans + a[i]) % MOD;
        System.out.format("%d\n", ans);
    }
}

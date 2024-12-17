import java.util.Scanner;

public class Multi {
    public static void main(String[] args) {
        Scanner scanner = new Scanner(System.in);
        int n = scanner.nextInt();

        int []arr = new int[n];
        for (int i = 0; i < n; i++) {
            arr[i] = scanner.nextInt();
        }

        long[] product = new long[n];
        long p = 1;
        for (int i = 0; i < n; i++) {
           product[i] = p;
           p*=arr[i];
        }

        p = 1;
        for (int i = n - 1; i >= 0 ; i--) {
            product[i] *= p;
            p*=arr[i];
        }

        for (int i = 0; i < n; i++) {
            System.out.print(product[i] % 1_000_000_000 + " ");
        }
    }
}

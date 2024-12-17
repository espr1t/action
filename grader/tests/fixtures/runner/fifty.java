import java.util.Scanner;

public class fifty {
    public static void main(String[] args) {
        Scanner scan = new Scanner(System.in);
        int sizeInBytes = scan.nextInt();
        int[] a = new int[sizeInBytes / 4];
        a[0] = 42;
        for (int i = 1; i < a.length; i++)
            a[i] = (a[i - 1] * 13 + 17) % 123456789;
        System.out.println(a[0] ^ a[a.length / 2] ^ a[a.length - 1]);
    }
}

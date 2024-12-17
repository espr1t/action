import java.util.Scanner;

public class ABProblem {
    public static void main(String[] args) {
        Scanner scan = new Scanner(System.in);
        double a = scan.nextDouble();
        double b = scan.nextDouble();
        System.out.format("%.9f\n", a * b);
    }
}
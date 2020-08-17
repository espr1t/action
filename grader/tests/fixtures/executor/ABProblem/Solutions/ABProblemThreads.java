import java.util.ArrayList;
import java.util.List;
import java.util.Scanner;
import java.util.concurrent.*;

public class ABProblem {
    private static void calc() {
        long num = 42, sum = 0;
        for (int i = 0; i < 20000000; i++) {
            sum += num;
            num = (num * 1234567 + 426661337) % 1000000007;
        }
        System.err.println(sum);
    }

    public static void main(String[] args) throws ExecutionException, InterruptedException {
        long sTime = System.currentTimeMillis();
        Scanner scan = new Scanner(System.in);
        double a = scan.nextDouble();
        double b = scan.nextDouble();
        System.out.format("%.9f\n", a * b);

        List<Future<?>> futures = new ArrayList<>();
        int numExtraExecutors = (a < 0 || b < 0) ? 8 : 1;
        ThreadPoolExecutor executor = (ThreadPoolExecutor)Executors.newFixedThreadPool(numExtraExecutors);
        for (int i = 0; i < numExtraExecutors; i++) {
            futures.add(executor.submit(ABProblem::calc));
        }
        for (Future<?> future : futures) {
            future.get();
        }
        executor.shutdown();

        long eTime = System.currentTimeMillis();
        System.err.println("Execution time: " + ((eTime - sTime) / 1000.0));
    }
}
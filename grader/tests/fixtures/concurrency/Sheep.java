import java.io.File;
import java.io.FileNotFoundException;
import java.util.Arrays;
import java.util.Scanner;
import java.util.TreeSet;
import java.util.stream.IntStream;

public class Sheep {
    private final int MAX_WEIGHT = 2000;

    private boolean eval(TreeSet<Integer> treeSet, int[] count, int maxRuns, int capacity) {
        int[] cnt = Arrays.copyOf(count, count.length);
        TreeSet <Integer> have = new TreeSet<>(treeSet);

        for (int run = 0; run < maxRuns; run++) {
            int rem = capacity;
            while (rem > 0) {
                Integer next = have.headSet(Math.min(rem + 1, MAX_WEIGHT + 2)).last();
                if (next == -1) break;
                while (cnt[next] > 0 && rem >= next) {
                    rem -= next;
                    cnt[next]--;
                }
                if (cnt[next] <= 0) {
                    have.remove(next);
                    if (have.size() == 1)
                        return true;
                }
            }
        }
        return false;
    }

    private int minCapacity(int[] sheep, int maxRuns) {
        TreeSet<Integer> treeSet = new TreeSet<>();
        int[] count = new int[MAX_WEIGHT + 2];
        treeSet.add(-1);
        for (int i = 0; i < sheep.length; i++) {
            treeSet.add(sheep[i]);
            count[sheep[i]]++;
        }
        int capacity = Math.max(IntStream.of(sheep).sum() / maxRuns,
                                IntStream.of(sheep).max().getAsInt());
        while (!eval(treeSet, count, maxRuns, capacity))
            capacity++;
        return capacity;
    }

    public static void main(String[] args) throws FileNotFoundException {
        Scanner scan = new Scanner(System.in);
        // Scanner scan = new Scanner(new File("Sheep.in"));

        String[] tokens;
        tokens = scan.nextLine().split("\\s+");
        int numSheep = Integer.parseInt(tokens[0]);
        int maxRuns = Integer.parseInt(tokens[1]);
        int[] sheep = new int[numSheep];
        tokens = scan.nextLine().split("\\s+");
        for (int i = 0; i < numSheep; i++)
            sheep[i] = Integer.parseInt(tokens[i]);

        Sheep obj = new Sheep();
        System.out.println(obj.minCapacity(sheep, maxRuns));
    }
}

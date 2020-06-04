import java.io.File;
import java.io.FileNotFoundException;
import java.util.Arrays;
import java.util.Scanner;

public class Sheep {
    private final int MAX_WEIGHT = 2000;
    private final int TREE_SIZE = 4096;


    private int[] tree, cnt;
    private int[] saveTree, saveCnt;

    private void update(int val) {
        int idx = val + (TREE_SIZE >> 1);
        cnt[idx]++;

        while (idx > 0) {
            tree[idx] = Math.max(tree[idx], val);
            idx >>= 1;
        }
    }

    private void erase(int val) {
        int idx = val + (TREE_SIZE >> 1);
        cnt[idx]--;

        if (cnt[idx] == 0) {
            tree[idx] = -1; idx >>= 1;
            while (idx > 0) {
                tree[idx] = Math.max(tree[idx << 1], tree[(idx << 1) + 1]);
                idx >>= 1;
            }
        }
    }

    private int query(int idx) {
        idx += (TREE_SIZE >> 1);
        int ans = tree[idx];
        int flag = idx & 1; idx >>= 1;
        while (idx > 0) {
            if (flag == 1) ans = Math.max(ans, tree[idx << 1]);
            flag = idx & 1; idx >>= 1;
        }
        return ans;
    }

    private boolean eval(int[] sheep, int maxRuns, int capacity) {
        tree = Arrays.copyOf(saveTree, saveTree.length);
        cnt = Arrays.copyOf(saveCnt, saveCnt.length);

        int left = sheep.length;
        for (int run = 0; run < maxRuns; run++) {
            int rem = capacity;
            while (left > 0) {
                int num = query(Math.min(MAX_WEIGHT, rem));
                if (num == -1) break;
                rem -= num; erase(num); left--;
            }
            if (left == 0) return true;
        }
        return false;
    }

    private int minCapacity(int[] sheep, int maxRuns) {
        tree = new int[TREE_SIZE];
        Arrays.fill(tree, -1);
        cnt = new int[TREE_SIZE];
        Arrays.fill(cnt, 0);

        int weightSum = 0;
        for (int i = 0; i < sheep.length; i++) {
            update(sheep[i]);
            weightSum += sheep[i];
        }
        saveTree = Arrays.copyOf(tree, tree.length);
        saveCnt = Arrays.copyOf(cnt, cnt.length);
        int initCap = weightSum / maxRuns;
        while (!eval(sheep, maxRuns, initCap)) initCap++;
        return initCap;
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

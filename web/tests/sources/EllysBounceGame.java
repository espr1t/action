import java.io.BufferedReader;
import java.io.File;
import java.io.FileNotFoundException;
import java.io.FileReader;
import java.io.IOException;
import java.util.Arrays;

/*public class EllysBounceGame {
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
*/

    public long getScore(int[] tiles) {
        long ans = 0;


       // for (int i = 0; i < tiles.length; i++) {
       //     ans = Math.max(ans, simulate(Arrays.copyOf(tiles, tiles.length), i, -1));
       //     ans = Math.max(ans, simulate(Arrays.copyOf(tiles, tiles.length), i, +1));
       // }
        return ans;
    }

    public String checkData(int[] tiles) {
        if (tiles.length < 1 || tiles.length > 2000)
            return "tiles must contain between 1 and 2000 elements, inclusive.";
        for (int i = 0; i < tiles.length; i++) {
            if (tiles[i] < 1 || tiles[i] > 1000000000)
                return "Each element of tiles must be between 1 and 1,000,000,000, inclusive.";
        }
        return "";
    }

    public static int[] parseVectorInt(String line) {
        line = line.replace("{", "");
        line = line.replace("}", "");
        line = line.replace(" ", "");
        String[] tokens = line.split(",");
        int[] ret = new int[tokens.length];
        for (int i = 0; i < ret.length; i++)
            ret[i] = Integer.parseInt(tokens[i]);
        return ret;
    }

    public static void main(String[] args) throws FileNotFoundException, IOException {
        File inpFile = new File("D:\\espr1t\\myTasks\\Ready\\TopCoder.CRX.500.EllysBounceGame\\EllysBounceGame.in");
        BufferedReader in = new BufferedReader(new FileReader(inpFile));
        
        int numTests = Integer.parseInt(in.readLine());
        for (int curTest = 0; curTest < numTests; curTest++) {
            int[] tiles = parseVectorInt(in.readLine());
            
            long sTime = System.currentTimeMillis();
            EllysBounceGame obj = new EllysBounceGame();
            if (obj.checkData(tiles) != "")
                System.out.println(obj.checkData(tiles));
            long ans = obj.getScore(tiles);
            System.out.println(ans);
            long eTime = System.currentTimeMillis();
            System.out.println("Execution time: " + ((eTime - sTime) / 1000.0) + " on test " + curTest);
        }
        in.close();
    }
}
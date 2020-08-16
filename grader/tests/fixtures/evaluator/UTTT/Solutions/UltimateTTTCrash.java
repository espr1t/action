import java.util.Random;

public class Main {
	static Random rand = new Random();

	public Main() {
		System.out.printf("%d %d %d %d", getRandomNumber(), getRandomNumber(), getRandomNumber(), getRandomNumber());
	}

	static int getRandomNumber() {

		return rand.nextInt(2) - 1;
	}
}
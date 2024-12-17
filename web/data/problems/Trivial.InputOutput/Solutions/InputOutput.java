import java.util.Scanner;

public class Main {
	
	public static void main(String[] args) {
		Scanner sc = null;
		try {
			sc = new Scanner(System.in);
			String type = sc.nextLine();
			if(type.equals("String")) {
				String str = sc.nextLine();
				for(int i = str.length() - 1; i >= 0; --i) {
					System.out.print(str.charAt(i));
				}
			} else if (type.equals("Array")) {
				String str = sc.nextLine();
				String[] nums = str.split(" ");
				for(int i = nums.length - 1; i > 0; --i) {
					System.out.print(nums[i] + " ");
				}
				if(nums.length > 0) {
					System.out.print(nums[0]);
				}
			}
		} finally {
			if(sc != null) {
				sc.close();
			}
		}
	}
	
}
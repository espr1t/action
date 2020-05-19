class Alex {
    String getName() {
        return "Alex";
    }
}

public class HelloWorld {
    public static void main(String[] args) {
        Alex alex = new Alex();
        System.out.format("Hello, " + alex.getName() + "!\n");
    }
}

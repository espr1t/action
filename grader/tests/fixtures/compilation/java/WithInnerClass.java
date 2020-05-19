public class WithInnerClass {
    static class InnerClass {
        public void print() {
            System.out.format("In nested class.\n");
        }
    }

    public static void main(String[] args) {
        System.out.format("Hello, World!\n");
        HelloWorld.InnerClass inner = new HelloWorld.InnerClass();
        inner.print();
    }
}

#include <cstdio>
#include <thread>
#include <chrono>

int main(void) {
    int sum = 0;

    sum += 13;
    std::this_thread::sleep_for(std::chrono::milliseconds(100));
    sum += 17;
    std::this_thread::sleep_for(std::chrono::milliseconds(100));
    sum += 42;
    std::this_thread::sleep_for(std::chrono::milliseconds(100));
    sum += 666;
    std::this_thread::sleep_for(std::chrono::milliseconds(100));
    sum += 1337;

    printf("%d\n", sum);
    return 0;
}

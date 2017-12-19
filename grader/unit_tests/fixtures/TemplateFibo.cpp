/*
ID: espr1t
TASK: Template Fibo
INFO: Source to test slow compilations.
      Idea taken from: https://randomascii.wordpress.com/2014/03/10/making-compiles-slow/
*/

#include <cstdio>
const int FIB = 27;

template <int N, int M> struct TemplateFibo {
    enum {
        value = TemplateFibo<N - 1, M>::value + TemplateFibo<N - 2, M | (1 << N)>::value
    };
}; 

template <int M> struct TemplateFibo<1, M> {
    enum {value = 1};
};

template <int M> struct TemplateFibo<2, M> {
    enum {value = 1};
};
 
int main(void) {
    fprintf(stdout, "The %d-th Fibonacci number is: %d.\n", FIB, TemplateFibo<FIB, 0>::value);
    return 0;
}


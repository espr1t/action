#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <wait.h>
#include <errno.h>
#include <sys/time.h>
#include <sys/resource.h>

double getTime(struct timeval t) {
    return (t.tv_sec * 1000000.0 + t.tv_usec) / 1000000.0;
}

int main(int argc, char** argv) {
    struct timeval start_time, end_time;

    gettimeofday(&start_time, NULL);
    long exit_code = WEXITSTATUS(system(argv[1])) % 128;
    gettimeofday(&end_time, NULL);

    struct rusage info;
    getrusage(RUSAGE_CHILDREN, &info);

    double userTime = getTime(info.ru_utime);
    double sysTime = getTime(info.ru_stime);
    double clockTime = getTime(end_time) - getTime(start_time);

    fprintf(stderr, "%li -- exit code\n", exit_code);
    fprintf(stderr, "%.3lf -- user time (seconds)\n", userTime);
    fprintf(stderr, "%.3lf -- sys time (seconds)\n", sysTime);
    fprintf(stderr, "%.3lf -- clock time (seconds)\n", clockTime);
    fprintf(stderr, "%lld -- max resident set size (bytes)\n", 1024LL * info.ru_maxrss);
    fprintf(stderr, "%lld -- shared resident set size (bytes)\n", 1024LL * info.ru_ixrss);
    fprintf(stderr, "%lld -- unshared data size (bytes)\n", 1024LL * info.ru_idrss);
    fprintf(stderr, "%lld -- unshared stack size (bytes)\n", 1024LL * info.ru_isrss);
    fprintf(stderr, "%li -- number of swaps\n", info.ru_nswap);
    fprintf(stderr, "%li -- soft page faults\n", info.ru_minflt);
    fprintf(stderr, "%li -- hard page faults\n", info.ru_majflt);
    fprintf(stderr, "%li -- number of input blocks\n", info.ru_inblock);
    fprintf(stderr, "%li -- number of output blocks\n", info.ru_oublock);
    fprintf(stderr, "%li -- number of messages sent\n", info.ru_msgsnd);
    fprintf(stderr, "%li -- number of messages received\n", info.ru_msgrcv);
    fprintf(stderr, "%li -- number of signals\n", info.ru_nsignals);
    fprintf(stderr, "%li -- voluntary context switches\n", info.ru_nvcsw);
    fprintf(stderr, "%li -- involuntary context switches\n", info.ru_nivcsw);

    return 0;
}

import os
import config


NUM_CPUS = config.MAX_PARALLEL_EXECUTORS
NUM_CACHE_WAYS = 11
MAX_MEMORY_BANDWIDTH = 100


def set_rdt(path, cpu, mem, llc):
    print("  >> configuring '{}'...".format(path))
    if not os.path.exists(path):
        os.system("sudo mkdir {path}".format(path=path))
    os.system("echo '{cpu}' | sudo tee {path}/cpus_list".format(cpu=cpu, path=path))
    os.system("echo 'MB:0={mem}' | sudo tee {path}/schemata".format(mem=mem, path=path))
    os.system("echo 'L3:0={llc}' | sudo tee {path}/schemata".format(llc=format(llc, 'x'), path=path))


def set_rdt_limits():
    # Mount the Intel RDT Linux control filesystem
    if not os.path.ismount("/sys/fs/resctrl"):
        print("Mounting resctrl filesystem...")
        os.system("sudo mount -t resctrl resctrl /sys/fs/resctrl")
    else:
        print("Filesystem resctrl already mounted.")

    # Calculate and set the default values (for COS0)
    cache_ways = NUM_CACHE_WAYS // NUM_CPUS
    cache_mask = (1 << cache_ways) - 1
    print("Using {}/{} LLC ways...".format(cache_ways, NUM_CACHE_WAYS))

    print("Starting configuration...")
    for i in range(NUM_CPUS):
        set_rdt("/sys/fs/resctrl/COS{}".format(i + 1), i, 10, cache_mask)
        cache_mask <<= cache_ways
    print("Done!")


if __name__ == "__main__":
    set_rdt_limits()

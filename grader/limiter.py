"""
Specifies resource limitations per core (for newer Intel processors only). More info:
https://www.kernel.org/doc/html/latest/x86/resctrl_ui.html
https://software.intel.com/content/www/us/en/develop/articles/use-intel-resource-director-technology-to-allocate-last-level-cache-llc.html
To enable L3 (not enabled by default), you should edit the Linux command line:
  >> sudo vi /etc/default/grub
  >> append "rdt=cmt,l3cat,mba" to GRUB_CMDLINE_LINUX
"""

import os
import config
import common

logger = common.get_logger(__file__)


NUM_CACHE_WAYS = 11  # TODO: Get this automatically from /sys/fs/resctrl/info/L3/cbm_mask
MAX_MEMORY_BANDWIDTH = 100  # In percent


def set_rdt(cos, cpus, mem, llc):
    path = "/sys/fs/resctrl/COS{}".format(cos)
    logger.info("  >> configuring '{}'...".format(path))
    logger.info("    -- cpus: {}".format(cpus))
    logger.info("    -- memory: {}%".format(mem))
    logger.info("    -- LLC mask: {}".format(format(llc, "0{}b".format(NUM_CACHE_WAYS))))

    cpu_list = ",".join([str(cpu) for cpu in cpus])
    mem_config = "MB:{}".format(";".join(["{}={}".format(i, mem) for i in range(len(cpus))]))
    llc_config = "L3:{}".format(";".join(["{}={}".format(i, format(llc, "x")) for i in range(len(cpus))]))

    if not os.path.exists(path):
        if os.system("sudo mkdir {path}".format(path=path)) != 0:
            logger.warning("Could not create path {}".format(path))
    if os.system("echo '{cpu_list}' | sudo tee {path}/cpus_list".format(cpu_list=cpu_list, path=path)) != 0:
        logger.warning("Could not set cpu_list for {}".format(path))
    if os.system("echo '{mem_config}' | sudo tee {path}/schemata".format(mem_config=mem_config, path=path)) != 0:
        logger.warning("Could not set memory bandwidth for {}.".format(path))
    if os.system("echo '{llc_config}' | sudo tee {path}/schemata".format(llc_config=llc_config, path=path)) != 0:
        logger.warning("Could not set cache allocation for {}.".format(path))


def set_rdt_limits():
    # Mount the Intel RDT Linux control filesystem
    if not os.path.ismount("/sys/fs/resctrl"):
        logger.info("Mounting resctrl filesystem...")
        if os.system("sudo mount -t resctrl resctrl /sys/fs/resctrl 2> /dev/null") != 0:
            logger.warning("Couldn't mount resctrl filesystem. Skipping RDT limitation...")
            return
    else:
        logger.info("Filesystem resctrl already mounted.")

    # Clean old configurations if present
    logger.info("Cleaning old configurations (if any)...")
    for cos in range(100):
        path = "/sys/fs/resctrl/COS{}".format(cos)
        if os.path.exists(path):
            if os.system("sudo rmdir {}".format(path)) != 0:
                logger.warning("Could not remove folder {}".format(path))

    # Print miscellaneous information about current setup
    logger.info("Number of CPU sockets (set in config.py): {}".format(config.NUM_PHYSICAL_CPUS))
    logger.info("Number of workers (set in config.py): {}".format(config.MAX_PARALLEL_WORKERS))

    cores_per_cpu = config.MAX_PARALLEL_WORKERS // config.NUM_PHYSICAL_CPUS
    if config.MAX_PARALLEL_WORKERS % config.NUM_PHYSICAL_CPUS != 0:
        cores_per_cpu += 1
    logger.info("Using {} cores per CPU.".format(cores_per_cpu))

    mem_percent = 100 // cores_per_cpu - 100 // cores_per_cpu % 10
    logger.info("Memory percentage of each core: {}%".format(mem_percent))

    cache_ways = NUM_CACHE_WAYS // cores_per_cpu
    cache_mask = (1 << cache_ways) - 1
    logger.info("Using {}/{} LLC ways per core.".format(cache_ways, NUM_CACHE_WAYS))
    cpus = list(range(0, config.NUM_PHYSICAL_CPUS))

    # Don't change the default configuration, it is used by all remaining cores.
    # Only create custom COSX folders for cores we will use.
    for cos in range(1, cores_per_cpu + 1):
        set_rdt(cos, cpus, mem_percent, cache_mask)
        cpus = [cpu + config.NUM_PHYSICAL_CPUS for cpu in cpus]
        cache_mask <<= cache_ways
    logger.info("Resource limiting set up successfully!")


if __name__ == "__main__":
    set_rdt_limits()

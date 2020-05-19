from subprocess import Popen
p1 = Popen(args="factor {}".format(1000000000000037 * 1000000000000091), executable="/bin/bash", shell=True)
p2 = Popen(args="factor {}".format(1000000000000037 * 1000000000000159), executable="/bin/bash", shell=True)
p1.wait(); p2.wait()

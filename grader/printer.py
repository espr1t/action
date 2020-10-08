import re
import random
import string

import common
from sandbox import Sandbox
from runner import Runner

logger = common.get_logger(__file__)


class Printer:
    @staticmethod
    def validate_url(url):
        regex = re.compile(
            r'^(?:http|ftp)s?://'  # http:// or https://
            r'(?:(?:[A-Z0-9](?:[A-Z0-9-]{0,61}[A-Z0-9])?\.)+(?:[A-Z]{2,6}\.?|[A-Z0-9-]{2,}\.?)|'  # domain...
            r'localhost|'  # localhost...
            r'\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})'  # ...or ip
            r'(?::\d+)?'  # optional port
            r'(?:/?|[/?]\S+)$', re.IGNORECASE)
        return re.match(regex, url) is not None

    @staticmethod
    def get_pdf(url):
        if not Printer.validate_url(url):
            return None
        sandbox = Sandbox()
        Runner.run_command(
            sandbox=sandbox, command="wkhtmltopdf", timeout=10, args=[url, "output.pdf"], privileged=True
        )

        # Hack for WSL2 which has this as a left-over
        if sandbox.has_file("../root/.cache/gstreamer-1.0/registry.x86_64.bin"):
            sandbox.del_file("../root/.cache/gstreamer-1.0/registry.x86_64.bin")

        # Check if the output is properly produced and return an error if it is not.
        if not sandbox.has_file("output.pdf"):
            return None

        # Copy the file from the sandbox to a temp folder and send it back to the user from there
        pdf_path = "/tmp/{}.pdf".format("".join(random.choice(string.digits) for _ in range(10)))
        sandbox.get_file("output.pdf", pdf_path)
        return pdf_path

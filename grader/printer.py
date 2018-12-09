import re
import random
import string
import subprocess


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
        pdf_path = "/tmp/{}.pdf".format("".join(random.choice(string.digits) for _ in range(10)))
        try:
            subprocess.run(["wkhtmltopdf", url, pdf_path], stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=10)
        except subprocess.TimeoutExpired:
            return None
        return pdf_path

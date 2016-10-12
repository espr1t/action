import logging

logging.basicConfig(level=logging.INFO)
logging.getLogger("requests").setLevel(logging.WARN)
logging.getLogger("werkzeug").setLevel(logging.WARN)

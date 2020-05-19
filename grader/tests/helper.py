import json
from evaluator import Evaluator


def get_evaluator(data_file):
    with open(data_file) as file:
        data = json.loads(file.read())
        return Evaluator(data)

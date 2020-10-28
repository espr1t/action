clean:
	sudo --preserve-env=PATH env python initializer.py clean
	sudo rm -rf Action.egg-info
	sudo rm -rf build
	sudo rm -rf .pytest_cache
	sudo rm -rf __pycache__
	sudo rm -rf tests/__pycache__

install:
	pip install .

init:
	sudo --preserve-env=PATH env python initializer.py init

start run:
	sudo --preserve-env=PATH env python server.py

test:
#	sudo env RUNNING_TESTS=true python3 -m pytest -rA tests/test_evaluator.py
# 	sudo env RUNNING_TESTS=true python3 -m pytest -rA --full-trace tests/test_evaluator.py
	sudo env RUNNING_TESTS=true python3 -m pytest -rA tests/

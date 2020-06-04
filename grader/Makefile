# Use "--preserve-env=PATH env" after sudo in case you depend on local user paths (e.g. pyenv)

clean:
	sudo python3 initializer.py clean
	sudo rm -rf Action.egg-info
	sudo rm -rf build
	sudo rm -rf .pytest_cache
	sudo rm -rf __pycache__
	sudo rm -rf tests/__pycache__

install: clean
	pip3 install .

init:
	sudo python3 initializer.py init

start run:
	sudo python3 server.py

test:
	sudo env RUNNING_TESTS=true python3 -m pytest -rA tests/test_evaluator.py
#	sudo env RUNNING_TESTS=true python3 -m pytest -rA tests/test_concurrency.py
#	sudo env RUNNING_TESTS=true python3 -m pytest -rA tests/

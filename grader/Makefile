# Use "--preserve-env=PATH env" after sudo in case you depend on local user paths (e.g. pyenv)

clean:
	sudo --preserve-env=PATH env python3 initializer.py clean
	sudo rm -rf Action.egg-info
	sudo rm -rf build
	sudo rm -rf .pytest_cache
	sudo rm -rf __pycache__
	sudo rm -rf tests/__pycache__

install:
	pip install .

init:
	sudo --preserve-env=PATH env python3 initializer.py init

start run:
	sudo --preserve-env=PATH env python3 server.py

test:
	sudo --preserve-env=PATH env RUNNING_TESTS=true pypy -m pytest -rA tests/
#	sudo --preserve-env=PATH env RUNNING_TESTS=true pypy -m pytest -rA --full-trace tests/test_evaluator.py
#	sudo --preserve-env=PATH env RUNNING_TESTS=true pypy -m pytest -rA --full-trace tests/test_compiler.py
#	sudo --preserve-env=PATH env RUNNING_TESTS=true pypy -m pytest -rA --full-trace tests/test_sandbox.py

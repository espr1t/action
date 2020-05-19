# Use "--preserve-env=PATH env" after sudo in case you depend on local user paths (e.g. pyenv)

install:
	sudo pip3 install .

init:
	sudo python3 initializer.py init

clean:
	sudo python3 initializer.py clean
	sudo rm -rf .pytest_cache
	sudo rm -rf Action.egg-info

start run:
	sudo python3 server.py

test:
	sudo env RUNNING_TESTS=true python3 -m pytest -rA tests/test_runner.py

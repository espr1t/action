from setuptools import setup, find_packages

setup(
    name="Action",
    version="1.1",
    description="A powerful back-end for programming competition systems.",
    author="Alexander Georgiev",
    author_email="thinkcreative@outlook.com",
    keywords="Competitive programming, Sandbox, Evaluation",
    url="http://action.informatika.bg",
    project_urls={
        "Bug Tracker": "http://bugs.informatika.bg",
        "Source Code": "https://github.com/espr1t/action",
    },

    packages=find_packages(),

    install_requires=[
        "Flask~=3.0.2",
        "urllib3~=2.2.1",
        "requests~=2.32.3",
        "vcrpy~=6.0.1",
        "pyflakes~=3.2.0",
        "psutil~=5.9.8",
        "pytest~=8.1.1",
        "markupsafe~=2.1.5",
        "pytest-order~=1.2.0",
    ],
)

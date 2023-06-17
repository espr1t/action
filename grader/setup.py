from setuptools import setup, find_packages

setup(
    name="Action",
    version="1.0",
    description="A powerful back-end for programming competition systems.",
    author="Alexander Georgiev",
    author_email="thinkcreative@outlook.com",
    keywords="Competitive programming, Sandbox, Evaluation",
    url="http://action.informatika.bg",
    project_urls={
        "Bug Tracker": "http://www.espr1t.net/bugs",
        "Source Code": "https://github.com/espr1t/action",
    },

    packages=find_packages(),

    install_requires=[
        "Flask~=2.3.2",
        "urllib3~=1.26.16",
        "requests~=2.31.0",
        "vcrpy~=4.3.1",
        "pyflakes~=3.0.1",
        "psutil~=5.9.5",
        "pytest~=7.3.1",
        "numpy~=1.24.3",
        "markupsafe~=2.1.2",
    ],
)

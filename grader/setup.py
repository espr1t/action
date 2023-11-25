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
        "Flask~=2.3.3",
        "urllib3~=2.1.0",
        "requests~=2.31.0",
        "vcrpy~=5.1.0",
        "pyflakes~=3.1.0",
        "psutil~=5.9.6",
        "pytest~=7.4.3",
        "numpy~=1.26.2",
        "markupsafe~=2.1.3",
    ],
)

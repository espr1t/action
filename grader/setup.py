from setuptools import setup, find_packages

setup(
    name="Action",
    version="1.2",
    description="A powerful back-end for programming competition systems.",
    author="Alexander Georgiev",
    author_email="thinkcreative@outlook.com",
    keywords="Competitive programming, Sandbox, Evaluation",
    url="https://action.informatika.bg",
    project_urls={
        "Source Code": "https://github.com/espr1t/action",
    },

    packages=find_packages(),

    install_requires=[
        "Flask~=3.1.3",
        "urllib3~=2.7.0",
        "requests~=2.34.2",
        "vcrpy~=8.3.0",
        "pyflakes~=3.4.0",
        "psutil~=7.2.2",
        "pytest~=9.1.1",
        "markupsafe~=3.0.3",
        "pytest-order~=1.5.0",
        "psutil~=7.2.2",
        "numpy~=2.5.2",
        "pandas~=3.0.5",
        "pyflakes~=3.4.0",
    ],
)

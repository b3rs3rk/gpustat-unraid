# Contributing to gpustat-unraid
We love your input! We want to make contributing to this project as easy and transparent as possible, whether it's:

- Reporting a bug
- Discussing the current state of the code
- Submitting a fix
- Proposing new features
- Becoming a maintainer

## We Develop with Github
We use github to host code, to track issues and feature requests, as well as accept pull requests.

## All Code Contribution Changes Will Happen Through Pull Requests
Pull requests are the best way to propose changes to the codebase.  We actively welcome your pull requests:

1. Fork the repo and create your branch from `dev`.  Pull requests to `master` in most situations will be rejected.
2. Make sure your code meets repository standards.
3. DO NOT attempt to run the mkpkg script in the src directory.  Repository maintainer will reject all PRs that include binary data other than images.
4. Issue that pull request!
5. Repository maintainer performs reviews and requests changes as needed.  Be prepared for this before any merge is approved.

## Any contributions you make will be under the MIT Software License
In short, when you submit code changes, your submissions are understood to be under the same [MIT License](http://choosealicense.com/licenses/mit/) that covers the project. Feel free to contact the maintainers if that's a concern.

## Report bugs using Github's [issues](https://github.com/b3rs3rk/gpustat-unraid/issues)
We use GitHub issues to track public bugs. Report a bug by opening a new issue it's that easy!

## Write bug reports with detail, background, and sample code
[This is an example](http://stackoverflow.com/q/12488905/180626) of a good bug report, and we think it's not a bad model. Here's [another example from Craig Hockenberry](http://www.openradar.me/11905408).

**Great Bug Reports** tend to have:

- A quick summary and/or background
- Steps to reproduce
  - Be specific!
  - Give sample code if you can. [This stackoverflow question](http://stackoverflow.com/q/12488905/180626) includes sample code that *anyone* can run to reproduce what they were seeing
- What you expected would happen
- What actually happens
- Notes (possibly including why you think this might be happening, or stuff you tried that didn't work)

People *love* thorough bug reports. I'm not even kidding.

## Use a Consistent Coding Style
Use existing repository code (that you should have already reviewed) as a guide

* PHP should use 4 spaces to denote a tab character
* camelCase any variables/functions
* Any new functions should have PHP Doc Blocks
* Function braces open on next line
* Avoid mixing languages in any one file if possible (put PHP, JS, and CSS in their own files in the proper directories)
* By design, all PHP classes for vendors extend the main class.  Any function that applies to multiple vendors should be in the main class

## License
By contributing, you agree that your contributions will be licensed under its MIT License.

## References
This document was originally adapted from the open-source contribution guidelines for [Facebook's Draft](https://github.com/facebook/draft-js/blob/a9316a723f9e918afde44dea68b5f9f39b7d9b00/CONTRIBUTING.md)

This document was copied and modified from [here](https://gist.githubusercontent.com/briandk/3d2e8b3ec8daf5a27a62/raw/8bc29dd83d0f7cc2d31f8c6741e787c95abb6497/CONTRIBUTING.md)

Texy!   [![Buy me a coffee](https://files.nette.org/images/coffee1s.png)](https://nette.org/make-donation?to=texy)
====

[![Downloads this Month](https://img.shields.io/packagist/dm/texy/texy.svg)](https://packagist.org/packages/texy/texy)
[![Tests](https://github.com/dg/texy/workflows/Tests/badge.svg?branch=master)](https://github.com/dg/texy/actions)
[![Coverage Status](https://coveralls.io/repos/github/dg/texy/badge.svg?branch=master)](https://coveralls.io/github/dg/texy?branch=master)
[![Latest Stable Version](https://poser.pugx.org/dg/texy/v/stable)](https://github.com/dg/texy/releases)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/dg/texy/blob/master/license.md)

Texy is a text-to-HTML engine for lightweight markup languages, written in PHP.
It turns human-readable plain text into structurally valid HTML – complete with
images, links, nested lists, and tables – no knowledge of HTML required.

Above all, Texy is an engine. It ships with its own rich, easy-to-read markup,
but nothing about that syntax is set in stone: every rule can be switched on or
off, redefined, or extended with your own. You can reshape Texy to speak a
different dialect entirely – and one of them already exists, see Markdown below.

And Texy is a typographer at heart. It swaps straight quotes for typographically
correct ones, hyphens for dashes, the letter x for the dimension sign, and applies
many more refinements so your text reads like it was professionally typeset.

Install Texy via Composer:

    composer require texy/texy

Texy requires PHP 8.2 or higher.


Usage
-----

    $texy = new Texy;
    $html = $texy->process($text);

For untrusted input, always enable safe mode, which restricts the output to a
safe subset of HTML:

    Texy\Configurator::safeMode($texy);
    $html = $texy->process($text);


Markdown
--------

[texy/markdown](https://github.com/dg/texy-markdown) is a companion package that
parses CommonMark and GitHub Flavored Markdown – the whole specification, all five
extensions – into **this same AST**:

    composer require texy/markdown

    $markdown = Texy\Markdown\Markdown::gfm();
    $html = $markdown->toHtml($text, $texy);

Because the tree is the same one, a Markdown document goes out through the renderer
above and gets everything Texy does to a Texy one: typography, HTML sanitization,
well-forming, Latte integration. The package renders a Texy AST back to Markdown as
well, so converting between the two languages is a matter of picking a parser and a
renderer.


Documentation and Examples
--------------------------

The full manual is on the homepage <https://texy.nette.org>. The [docs/](docs/)
directory holds the reference for using and extending Texy:

- **[Syntax reference](docs/syntax.md)** – complete reference of the Texy markup
  language, including syntaxes that are disabled by default
- **[Configuration](docs/configuration.md)** – all configuration options, the
  `Configurator` presets, and security (safe mode)
- **[Custom handlers](docs/custom-handlers.md)** – changing the behavior of
  existing constructs via renderer handlers and AST passes
- **[Custom syntax](docs/custom-syntax.md)** – adding brand-new markup constructs
- **[Upgrading](docs/upgrading.md)** – migration guide from Texy 3

The **[examples/](examples/)** directory contains ready-to-run demos.


[Support Me](https://github.com/sponsors/dg)
--------------------------------------------

Do you like Texy? Are you looking forward to the new features?

[![Buy me a coffee](https://files.nette.org/icons/donation-3.svg)](https://github.com/sponsors/dg)

Thank you!

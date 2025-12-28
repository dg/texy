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
different dialect entirely – even Markdown.

And Texy is a typographer at heart. It swaps straight quotes for typographically
correct ones, hyphens for dashes, the letter x for the dimension sign, and applies
many more refinements so your text reads like it was professionally typeset.

Install Texy via Composer:

    composer require texy/texy

Texy requires PHP 8.1 or higher.


Usage
-----

    $texy = new Texy;
    $html = $texy->process($text);

For untrusted input, always enable safe mode, which restricts the output to a
safe subset of HTML:

    Texy\Configurator::safeMode($texy);
    $html = $texy->process($text);


Documentation and Examples
--------------------------

The full manual is on the homepage <https://texy.nette.org>. The [docs/](docs/)
directory holds the reference for using and extending Texy:

- **[Syntax reference](docs/syntax.md)** – complete reference of the Texy markup
  language, including syntaxes that are disabled by default
- **[Configuration](docs/configuration.md)** – all configuration options, the
  `Configurator` presets, and security (safe mode)
- **[Custom handlers](docs/custom-handlers.md)** – changing the behavior of
  existing constructs via element and notification handlers
- **[Custom syntax](docs/custom-syntax.md)** – adding brand-new markup constructs

The **[examples/](examples/)** directory contains ready-to-run demos.


[Support Me](https://github.com/sponsors/dg)
--------------------------------------------

Do you like Texy? Are you looking forward to the new features?

[![Buy me a coffee](https://files.nette.org/icons/donation-3.svg)](https://github.com/sponsors/dg)

Thank you!

# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.

## Documentation

Any distilled, agent-facing documentation for this package - how it works
internally and the rationale behind key design decisions - lives in `docs/`.
Consult it before non-trivial changes; it is the source of truth from which the
public manual is distilled.

The core is non-trivial - a two-parser pipeline, a protection-mark hierarchy,
chain-of-responsibility handlers, and DTD validation. Read the relevant
`docs/` seam before editing. Note the internals describe the current
**Texy 3.x** line; 4.0 will be AST-based and change most of it.

## Project Overview

Texy is a mature text-to-HTML converter: it turns plain text in Texy syntax into
valid (X)HTML with typography, images, links, tables, and lists, and integrates
with Latte.

- **PHP Version**: 8.2 - 8.5
- **Package**: `texy/texy`

## Essential Commands

```bash
# Run all tests
vendor/bin/tester tests -s        # or: composer tester
vendor/bin/tester tests/Texy/blocks.phpt -s

# Static analysis (PHPStan level 5, informative)
composer phpstan
```

## Conventions

- Every file starts with `declare(strict_types=1);`; Nette Coding Standard.
- Tests are Nette Tester `.phpt` comparing output against `tests/Texy/expected/*.html`
  from `tests/Texy/sources/*.texy` (via `Assert::matchFile`). Test files are named
  **`{subject}[-{aspect}].phpt`** (singular subject like `image`; aspect like
  `-reference`/`-handler`/`-syntax`), and expected files use descriptive suffixes,
  not numbers (`figure-nocaption.html`, not `figure2.html`).

## Working in this repo

- **Two parsers, in order:** `BlockParser` handles block structures (blocks never
  overlap), then `LineParser` handles inline syntaxes (nesting via progressive
  expansion). See `docs/parsing.md`.
- **Protection marks are a hierarchy, not just a mask.** Content-type bytes
  `\x14`-`\x1F` are ordered so `[\x17-\x1F]+` matches a whole MARKUP placeholder;
  paragraph detection, autolinks, longwords, and typography all read them. New
  patterns must exclude already-processed content (`[^\x14-\x1F]`), and raw HTML you
  emit must be wrapped via `$texy->protect($html, Texy\Texy::CONTENT_BLOCK)`.
- **Syntax collisions are resolved by registration order** (earlier pattern wins);
  some registration is lazy in `beforeParse`.
- **The handler chain runs last-registered-first**, with the module's default
  implementation last; a handler calls `$invocation->proceed()` to delegate.
- **Modules are wired one-directionally through value objects** (`Link`/`Image`);
  `HtmlOutputModule` fixes nesting/auto-closing, `Modifier::decorate` filters against
  the `allowed*` whitelists, and `HtmlElement` is DTD-validated.
- **Security: always run `Configurator::safeMode($texy)` for untrusted input** - it
  restricts HTML to a safe subset, disables classes/IDs/styles and images, filters
  URL schemes, and adds `rel="nofollow"`.
- User- and extender-facing how-to (Texy syntax, configuration, custom handlers,
  custom syntax registration, the modifier catalog) is manual material and lives in
  the public web docs, not here.

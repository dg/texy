# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.

## Documentation

Any distilled, agent-facing documentation for this package - how it works
internally and the rationale behind key design decisions - lives in `docs/`.
Consult it before non-trivial changes; it is the source of truth from which the
public manual is distilled.

The core is non-trivial - a two-parser pipeline building an AST, transform
passes, and content-model well-forming. Read the relevant `docs/` seam before
editing. The internals describe the current **4.0-dev** line (the AST design).

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

- **Four phases: preprocess → parse → transform → render.** Parsing builds an AST
  (`Texy\Nodes\*`); the transform phase (`afterParse` passes) may mutate it; the
  render phase (`Texy\Output\*`) must not - **rendering is a pure function of the
  AST** (enforced by `renderer-purity.phpt`). See `docs/architecture.md`.
- **Two parsers:** `BlockParser` handles block structures (blocks never overlap),
  `InlineParser` handles inline syntaxes; nesting happens only through explicit
  recursion via `ParseContext`, handler output is never re-scanned. Collisions:
  at the same offset the longer match wins, then earlier registration.
  See `docs/parsing.md`.
- **Configuration placement rule:** what changes the document's meaning for every
  format lives on `Texy`/modules; what only shapes HTML output lives on
  `$texy->htmlOutput` (modules keep deprecated property bridges).
- **`TextNode` holds decoded display text** (entities decoded at parse time);
  renderers escape on output. Code-like nodes keep literal source.
- **Typography and hyphenation are an AST pass** (`TextRunPass`); its regexes
  treat bytes `\x15`-`\x17` as a marker alphabet and must never create or
  destroy them.
- **Raw HTML in renderers:** return `Output\Html\Element` or `Output\Html\Raw`;
  `Generator::protect()` marks are a deprecated bridge for legacy custom handlers.
  Well-forming (pairing, content model, indentation) happens in `WellFormer`,
  fed by a walk of the rendered tree. See `docs/rendering.md`.
- **Security: always run `Configurator::safeMode($texy)` for untrusted input** - it
  restricts HTML to a safe subset, disables classes/IDs/styles and images, filters
  URL schemes, and adds `rel="nofollow"`. Tag sanitization happens in the transform
  phase (`HtmlSanitizePass`), so it protects every output format.
- User- and extender-facing how-to (Texy syntax, configuration, custom handlers,
  custom syntax registration, the modifier catalog) is manual material and lives in
  the public web docs, not here.

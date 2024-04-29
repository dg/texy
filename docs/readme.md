# Texy documentation

Agent-facing documentation for this package: a complete reference for using and
extending Texy, plus how it converts its markup to HTML underneath.

## Reference (using and extending Texy)

- **[syntax.md](syntax.md)** — complete reference of the Texy markup language,
  including syntaxes that are disabled by default.
- **[configuration.md](configuration.md)** — all configuration options: `Texy` and
  per-module properties with defaults, the `Configurator` presets, and security
  (safe mode).
- **[custom-handlers.md](custom-handlers.md)** — changing the behavior of existing
  constructs via element and notification handlers; full event reference with
  code-verified signatures.
- **[custom-syntax.md](custom-syntax.md)** — adding brand-new markup constructs with
  `registerLinePattern()` / `registerBlockPattern()`.

## Internals (how it works)

The core is non-trivial — a two-parser pipeline, a protection-mark hierarchy,
chain-of-responsibility handlers, and DTD validation — so it is split by seam:

- **[architecture.md](architecture.md)** — the four processing phases, the terminology
  (syntax / pattern / syntax handler / element handler / notification handler), and how
  the `Texy` orchestrator wires it together.
- **[parsing.md](parsing.md)** — `LineParser` vs `BlockParser`, the protection marks and
  their content-type hierarchy, syntax collisions, and post-line processing.
- **[modules.md](modules.md)** — the module system, the anatomy of a module, the overview
  of the built-in modules, and how they cooperate.
- **[html-element.md](html-element.md)** — the `HtmlElement` DOM tree and DTD validation.
- **[modifiers.md](modifiers.md)** — the `.(…)[…]{…}<>` modifier system and `decorate()`.

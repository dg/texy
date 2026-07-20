# Texy documentation

Agent-facing documentation for this package: a complete reference for using and
extending Texy, plus how it converts its markup to HTML underneath.

## Reference (using and extending Texy)

- **[syntax.md](syntax.md)** — complete reference of the Texy markup language,
  including syntaxes that are disabled by default.
- **[configuration.md](configuration.md)** — all configuration options: `Texy`,
  generator and per-module properties with defaults, the `Configurator` presets,
  and security (safe mode).
- **[custom-handlers.md](custom-handlers.md)** — changing how existing constructs
  are rendered (renderer handlers) and transforming the parsed document
  (`afterParse` + `NodeTraverser`).
- **[custom-syntax.md](custom-syntax.md)** — adding brand-new markup constructs with
  `registerLinePattern()` / `registerBlockPattern()`.

## Internals (how it works)

The core is non-trivial — a two-parser pipeline building an AST, transform
passes, and content-model well-forming — so it is split by seam:

- **[architecture.md](architecture.md)** — the four phases
  (preprocess → parse → transform → render), the purity and semantic-completeness
  invariants, the typography pass, and the terminology.
- **[parsing.md](parsing.md)** — `InlineParser` vs `BlockParser`, collision
  resolution, recursion through `ParseContext`, and the paragraph gap handler.
- **[modules.md](modules.md)** — the module system, the anatomy of a module, and
  the overview of the built-in modules.
- **[rendering.md](rendering.md)** — the `Output` layer: generators, the
  `Element`/`Raw` tree, sanitization, well-forming, and the deprecated
  protection-mark bridge.
- **[modifiers.md](modifiers.md)** — the `.(…)[…]{…}<>` modifier system and how
  modifiers decorate output elements.

# Architecture and Principles

Texy converts text written in its own markup language to HTML. Unlike simple converters that process text linearly with a series of replacements, Texy uses a system based on parsing, a modular architecture, and incremental building of a DOM tree.

Processing runs in four main phases:

1. **Preprocessing** – normalization of line endings and spaces, tab expansion, removal of soft hyphens, invocation of each module's `beforeParse()` method.
2. **Parsing** – recognition of syntaxes using regular expressions and incremental building of a DOM tree of `HtmlElement` objects.
3. **Post-processing** – typographic corrections, long-word hyphenation, HTML well-forming.
4. **Final assembly** – conversion of the DOM tree into the resulting HTML string.

The key difference from naive approaches is the separation of syntax *recognition* from syntax *processing*. The parser first identifies where each syntactic construct occurs in the text and only then hands the found parts over to individual modules. This allows syntaxes to nest and to be unwrapped step by step.

All classes live in the `Texy` namespace (so `HtmlElement` means `Texy\HtmlElement`); modules live in `Texy\Modules`.

## Key components

**The `Texy` class** (`src/Texy/Texy.php`) is the central orchestrator. It holds references to all modules, manages registered syntaxes and handlers, maintains processing state, and coordinates the conversion phases. It is the single place where components are wired together.

**[Modules](modules.md)** are functional units responsible for specific areas of the markup language. Each module registers, in its constructor, the syntaxes it recognizes and the element handlers that process them. For example `PhraseModule` handles inline formatting such as bold or italic text, while `TableModule` handles tables. Modules are designed as self-contained, reusable units with their own configuration exposed as public properties.

**[Parsers](parsing.md)** come in two variants by content type. `BlockParser` processes block structures such as paragraphs, headings, lists, or tables: it walks the text line by line, looks for the beginnings of block constructs, and passes them to *syntax handlers*. `InlineParser` handles inline syntaxes within lines – links, images, text formatting. Unlike `BlockParser`, it supports nesting of syntaxes and their gradual unwrapping.

## Terminology

To understand Texy you need to distinguish several key terms that recur throughout this documentation.

**Syntax** is a named syntactic construct of the markup language. Every syntax has a unique name, e.g. `phrase/strong` for bold text or `image` for images. The name is used to enable or disable the syntax in the `Texy::$allowed` array, and it is passed to syntax handlers so a shared callback can tell which syntax matched.

**Pattern** is the regular expression that defines what the syntax looks like in text. The pattern is an implementation detail of the syntax – the author must write a regex that recognizes it, but from the user's perspective the syntax name and meaning matter more. One module typically registers several syntaxes with different patterns.

**Syntax handler** is the function the parser calls when it finds an occurrence of the syntax in text. It receives the matched text and returns an `HtmlElement` or a string that is inserted back in place of the match. The syntax handler decides what happens with the found construct – typically it invokes an element handler for the actual processing.

**Element** is a kind of item for which an HTML representation is generated. For example `image` is the element for images, `linkURL` for autodetected links, `phrase` for inline formatting. Each element has a default element handler implemented in the owning module.

**Element handler** is a function registered for a given element type and invoked through the `HandlerInvocation` system. Its distinguishing feature is the `proceed()` method, which delegates to the next handler in the chain or to the module's default handler. Element handlers modify or replace the default behavior.

**Notification handler** is a function called to signal an event. Unlike element handlers it returns nothing and cannot influence the result of processing. It is used for preparing data, logging, or modifying an already-built DOM tree.

The distinction is crucial: a syntax handler is tightly coupled to the parser and to a specific pattern – it answers *"what to do when the parser finds this pattern"*. Element handlers sit at a higher level of abstraction – they answer *"how to process this kind of item"*, regardless of which concrete syntax produced it.

## Overall processing flow

When `Texy::process()` receives input text, the following happens (`src/Texy/Texy.php`, method `process()`):

1. **Preprocessing.** Soft hyphens (U+00AD) are removed (if `$removeSoftHyphens` is on), line endings and spaces are normalized (`Helpers::normalize()`), and tabs are expanded to spaces according to `$tabWidth`. Then each module's `beforeParse()` method is invoked with the text passed by reference – modules register their line and block patterns here and can preprocess data, e.g. `LinkModule` and `ImageModule` extract reference definitions and `TypographyModule` prepares locale-specific patterns.

2. **Pattern selection.** The registered line and block patterns are filtered by the `$allowed` array. This happens once per `process()` call, so changing `$allowed` during processing has no effect.

3. **Parsing.** A root `HtmlElement` representing the document is created. For a full document, `parseBlock()` creates a `BlockParser` that walks the text and identifies block constructs; text between blocks is handled by `ParagraphModule`, which internally uses `InlineParser` for the inline content. For `processLine()`, only `parseLine()`/`InlineParser` is used. Parsing incrementally builds the DOM tree.

4. **afterParse.** After parsing completes, `afterParse` notification handlers are invoked with the root element. They can perform final tree adjustments, e.g. `HeadingModule` assigns final heading levels, generates IDs and builds the TOC here.

5. **Serialization and post-processing.** `HtmlElement::toHtml()` converts the tree to a string. During this conversion each element is recursively rendered with HTML tags immediately masked by protection marks (see [parsing.md](parsing.md#protection-marks)). The resulting internal string is passed through `Texy::stringToHtml()`, which:
   - applies **post-line handlers** registered via `registerPostLine()` – typographic corrections (`typography`) and long-word hyphenation (`longwords`) – to the textual segments between block marks,
   - escapes `<`, `>`, `&` in remaining text,
   - replaces all protection marks with their real values (`unprotect()`),
   - invokes the `postProcess` notification event, which `HtmlOutputModule` uses to well-form and reformat the HTML (closing tags, fixing wrong nesting, indentation, line wrapping),
   - unfreezes spaces in attributes (see below).

The result is the final HTML string.

## Syntax system

A syntax is an abstract concept combining a unique name, a regular expression for recognition, and a way of processing. The name serves as the identifier throughout the system – in `Texy::$allowed`, in handler parameters, in documentation.

Naming follows two conventions. Simple syntaxes have a one-word name matching their purpose: `image`, `table`, `script`. More complex areas use hierarchical names with a slash: `phrase/strong`, `phrase/em`, `link/reference`. The slash groups related syntaxes logically.

There are three kinds of syntaxes, each with its own registration method on `Texy`:

- **Line syntaxes** (`registerLinePattern()`) recognize inline items within lines of text – formatting, links, images, inline code. They may nest inside each other and [`InlineParser`](parsing.md#inlineparser) unwraps them gradually. Their patterns are searched for anywhere in the text, so they must not be anchored.
- **Block syntaxes** (`registerBlockPattern()`) recognize multi-line block constructs: headings, lists, tables, quotes, special blocks. Unlike line syntaxes, block syntaxes never overlap – every line of text belongs to at most one block construct, and [`BlockParser`](parsing.md#blockparser) processes them without interleaving. Their patterns are anchored to the start of a line (the `m` modifier is added automatically).
- **Post-line syntaxes** (`registerPostLine()`) do not parse markup at all; they transform the final textual content between block-level protection marks just before HTML entities are encoded. Two modules use it: `TypographyModule` (name `typography`) and `LongWordsModule` (name `longwords`).

In all three cases the registered *syntax handler* returns an `HtmlElement`, a string, or `null` to refuse processing (post-line handlers return the transformed string). Registration parameters and handler signatures are documented in detail in the custom-syntax guide (user manual).

### Enabling and disabling syntaxes

The `Texy::$allowed` array gives fine-grained control over which syntaxes are active:

```php
$texy->allowed['phrase/strong'] = false;
```

`registerLinePattern()`, `registerBlockPattern()` and `registerPostLine()` default the entry to `true` if it is not set yet; a module can explicitly default a syntax to `false` (e.g. `emoticon`, `phrase/ins`, `phrase/del`, `phrase/sup`, `phrase/sub`). The check happens once at the start of parsing, so changing `$allowed` mid-processing has no effect.

The complete list of syntaxes with their default states is in the syntax reference (user manual); safe-mode presets are described in the configuration reference (user manual).

## Handler system

### Element handlers

Element handlers implement the chain-of-responsibility pattern, allowing the resulting behavior to be composed from multiple layers.

Registration uses `Texy::addHandler($elementName, $callback)`. One element name may have multiple handlers; they execute in order **from the last registered to the first**. Since modules register their default handlers in their constructors, a user handler registered later gets control first and decides whether the default handler runs at all.

Element names identify the kind of processing: `phrase`, `image`, `block`, `heading`... Sometimes compound names distinguish flavors, e.g. `linkReference`, `linkEmail`, `linkURL`, `newReference`. Element names are more general than syntax names – the `phrase` element covers all inline formatting syntaxes.

Invocation goes through `Texy::invokeAroundHandlers($event, $parser, $args)`, which wraps the registered handlers in a `HandlerInvocation` object (`src/Texy/HandlerInvocation.php`). The handler receives the `HandlerInvocation` as its first parameter followed by element-specific arguments, and controls the chain through `$invocation->proceed()`: calling it delegates onward (optionally with replaced parameters), not calling it breaks the chain.

See the custom-handlers guide (user manual) for the exact `proceed()` semantics, the full reference of elements with signatures, and practical examples.

### Notification handlers

Notification handlers use the same registration method, `Texy::addHandler($eventName, $callback)`, but are invoked with `Texy::invokeHandlers()`, which simply calls all registered handlers in registration order and ignores their return values. Handlers receive the invocation arguments but cannot change them for the following handlers (except for parameters explicitly passed by reference, such as the text in `beforeParse`).

The events are: `afterParse`, `beforeBlockParse`, `afterTable`, `afterList`, `afterDefinitionList`, `afterBlockquote`, and `postProcess`. Per-module preprocessing before parsing is not an event but the `Module::beforeParse()` method. Signatures are listed in the custom-handlers guide (user manual).

Unlike element handlers, notification handlers cannot prevent further processing – all registered handlers always run. That is intentional: notifications are about side effects, not flow control.

## Space freezing

To prevent line wrapping and other post-processing from corrupting HTML attribute values, spaces inside attributes are "frozen" during serialization using `Helpers::freezeSpaces()` (space → `\x01`, tab → `\x02`, `\r` → `\x03`, `\n` → `\x04`) and restored at the very end by `Helpers::unfreezeSpaces()` in `stringToHtml()`.


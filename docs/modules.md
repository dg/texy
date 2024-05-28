# The Module System

Modules are the basic organizational unit of Texy. Each module encapsulates the complete functionality for one area of the markup language: recognizing its syntaxes, converting them to HTML, and exposing configuration.

## Responsibilities of a module

1. **Registering syntaxes.** In its constructor a module calls `Texy::registerLinePattern()`, `registerBlockPattern()`, or `registerPostLine()` for every syntax it handles. This tells the parser: "when you find these patterns, call me."
2. **Implementing element handlers.** The module registers default handlers (via `Texy::addHandler()`) for the elements its syntaxes produce. These handlers contain the logic that turns matched constructs into `HtmlElement` objects.
3. **Providing configuration.** Public properties let users adjust the module's behavior without touching its code (e.g. `ImageModule::$root` for the image URL prefix).
4. **Managing module-specific state.** E.g. `HeadingModule` collects headings into its `$TOC` array; `LinkModule` maintains the dictionary of link references. This state is private to the module.

Modules are designed as independent units: each can work on its own and must not depend on the implementation details of other modules. Communication happens through shared value objects (`Texy\Link`, `Texy\Image`), not through direct method calls.

## Anatomy of a typical module

Every module extends the base class `Texy\Module` (`src/Texy/Module.php`), which holds the protected `$texy` back-reference. All initialization happens in the constructor:

```php
final class FooModule extends Texy\Module
{
    public string $someOption = 'default';   // public configuration

    public function __construct(Texy\Texy $texy)
    {
        $this->texy = $texy;

        $texy->allowed['foo/advanced'] = false;          // default off
        $texy->addHandler('foo', $this->solve(...));      // default element handler
        $texy->addHandler('beforeParse', $this->beforeParse(...)); // optional

        $texy->registerLinePattern(
            $this->patternFoo(...),   // syntax handler
            '#...#U',                 // pattern
            'foo',                    // syntax name
        );
    }
}
```

- **Syntax handlers** (`patternFoo()`) are called by the parser on a match. They extract data from the regex groups, build helper objects (`Link`, `Image`, `Modifier`), and invoke the element handler chain via `Texy::invokeAroundHandlers()`.
- **Element handlers** (`solve()`) do the actual work: create the `HtmlElement`, apply the modifier with `Modifier::decorate()`, process content, and return the result.
- **Public properties** are the configuration interface – typically primitive types or arrays.

The set of modules is created in `Texy::loadModules()`; a subclass of `Texy` may override this method to replace or extend the module set.

## Module overview

All modules live in `src/Texy/Modules/`. Registered syntax IDs and their defaults are detailed in the syntax reference (user manual); configurable properties in the configuration reference (user manual).

### Line (inline) modules

| Module | Purpose |
|---|---|
| **ScriptModule** | `{{command: args}}` macro calls; delegates entirely to user handlers of the `script` element. |
| **HtmlModule** | HTML tags (`html/tag`) and comments (`html/comment`) written directly in the input; validates them against `$allowedTags` and the DTD. |
| **ImageModule** | Images `[* url *]` including dimensions and alignment; maintains image reference definitions (`image/definition`, collected in `beforeParse`); invokes the `image` element. |
| **PhraseModule** | All inline formatting – bold, italic, code, spans, acronyms, sub/sup, quotes, and the alternative link syntaxes (wikilink, markdown, quicklink). Maps syntax names to HTML tags via its `$tags` property; a single `phrase` element handler serves all of them. |
| **LinkModule** | Reference links `[ref]`, autodetected URLs and e-mails; maintains the reference dictionary (`link/definition`, collected in `beforeParse`); provides `factoryLink()`; invokes `linkReference`, `linkURL`, `linkEmail`, and `newReference` elements. |
| **EmoticonModule** | Emoticons `:-)` → Unicode characters (or a `<span>` when a CSS class is set). Disabled by default; registers its pattern in `beforeParse`. |

### Block modules

| Module | Purpose |
|---|---|
| **ParagraphModule** | Not pattern-based: `BlockParser` hands it the text between recognized blocks and it produces paragraphs, invoking the `paragraph` element. Chooses the wrapper by the content's protection marks – `<p>` for text, `$texy->nontextParagraph` for replaced-only content, no wrapper around blocks (see [parsing.md](parsing.md#how-content-types-drive-behavior)). Honors `$texy->mergeLines`; converts hard line breaks to `<br>`. |
| **BlockModule** | Special fenced blocks `/-- type ... \--` (`blocks` syntax) with subtypes `block/code`, `block/html`, `block/text`, `block/texy`, `block/texysource`, `block/comment`, `block/div`, `block/pre`, `block/default`; normalizes fences in `beforeBlockParse`; invokes the `block` element. |
| **FigureModule** | Image with a visible caption `[* img *] *** caption` (`figure`); combines an `Image`, optional `Link`, and caption; invokes the `figure` element. |
| **HorizLineModule** | Horizontal rules `---` / `***` (`horizline`); type-specific CSS classes via `$classes`. |
| **BlockQuoteModule** | Quotations introduced by `>` (`blockquote`), including nested quotes and link citation; fires `afterBlockquote`. |
| **TableModule** | Tables (`table`) with head/body detection, row headers, colspan/rowspan (helper class `TableCellElement`), odd/even classes; fires `afterTable`. One of the most complex modules. |
| **HeadingModule** | Underlined (`heading/underlined`) and surrounded (`heading/surrounded`) headings; assigns levels in `afterParse` according to the balancing mode (`DYNAMIC`/`FIXED`), generates IDs, collects `$TOC` and `$title`; invokes the `heading` element. |
| **ListModule** | Bulleted, numbered (`list`) and definition (`list/definition`) lists; bullet styles configured via `$bullets`; registers its patterns in `beforeParse` (they depend on `$bullets`); fires `afterList` / `afterDefinitionList`. |

### Post-processing modules

| Module | Purpose |
|---|---|
| **TypographyModule** | Post-line handler `typography`: locale-aware quotes, en/em dashes, ellipsis, non-breaking spaces, arrows, ©®™, multiplication sign. Prepares locale patterns in `beforeParse`. |
| **LongWordsModule** | Post-line handler `longwords`: inserts `&shy;` soft hyphens into words longer than `$wordLimit`, using Czech-oriented syllable heuristics. |
| **HtmlOutputModule** | `postProcess` handler: makes the output well-formed (auto-closes tags, fixes nesting against the DTD), indents, and wraps long lines. |

## Interactions between modules

Although modules are independent, some cooperation is necessary:

**Shared value objects** are the main mechanism. A `Texy\Link` created by `LinkModule` can be handed to `ImageModule` to build a clickable image; a `Texy\Image` created by `ImageModule` is passed to `FigureModule` for a captioned figure. The objects carry the URL, modifier, and metadata, and expose a common interface.

**The reference system** separates definition from use. `LinkModule::addReference()` / `getReference()` manage the dictionary of named links; `ImageModule` has the same pair for images. Factory methods (`factoryLink()`, `factoryImage()`) check whether the given value is a reference name or a direct value.

**Element handler delegation.** `PhraseModule`, when processing e.g. `"text":url` (`phrase/span` with a link), creates a `Link` object and calls `LinkModule`'s handler to build the `<a>` element, delegating responsibility to the specialized module.

Relations are one-directional: `PhraseModule` knows about `LinkModule` and `ImageModule`, but not vice versa. This keeps dependencies simple and modules replaceable.

# Texy AST Architecture Migration Plan

## Úvod: Co se děje

Texy prochází zásadní architektonickou transformací z verze 3.x (HtmlElement-based) na verzi 4.0 (AST-based). Cílem je oddělit parsování od generování HTML, což umožní lepší rozšiřitelnost, testovatelnost a údržbu.

---

## Část 1: Legacy Architektura (Texy 3.x)

### Základní Princip

Legacy architektura je založena na **přímé konstrukci DOM stromu** během parsování. Když parser najde syntaxi, okamžitě vytváří `HtmlElement` objekty.

### Processing Pipeline

```
1. Preprocessing
   └── Normalizace textu, volání beforeParse handlerů

2. Parsing
   └── BlockParser/LineParser hledají syntaxe
   └── Syntax handlery OKAMŽITĚ vytvářejí HtmlElement objekty
   └── invokeAroundHandlers() volá element handlery
   └── Výsledek se vkládá zpět do textu (s protection marks)

3. Post-processing
   └── afterParse handlery modifikují DOM strom
   └── Typografické úpravy, longwords
   └── Well-forming HTML

4. Final Assembly
   └── HtmlElement.toString() → HTML string
   └── unProtect() → finální HTML
```

### Klíčové Komponenty

#### HtmlElement (511 řádků)
Slouží jako:
- **Reprezentace dat** - tag name, attrs, children
- **DOM strom** - add(), create(), insert()
- **HTML renderer** - toString(), startTag(), endTag()
- **Validátor** - validateAttrs(), validateChild()
- **Parser kontejner** - parseLine(), parseBlock()

```php
// Typické použití v legacy modulu
$el = new HtmlElement('h1');
$mod->decorate($texy, $el);           // Aplikace modifikátorů
$el->parseLine($texy, $content);      // Parsování obsahu
return $el;                            // Vrácení elementu
```

#### Handler System (Chain of Responsibility)

```php
// Element handler - volán přes invokeAroundHandlers()
$texy->addHandler('image', function(
    HandlerInvocation $invocation,
    Image $image,
    ?Link $link,
) {
    // Modifikace vstupu
    $image->width ??= 800;

    // Delegace na další handler
    $element = $invocation->proceed();

    // Modifikace výstupu
    $element->attrs['loading'] = 'lazy';

    return $element;
});
```

#### Modifier System

```php
// Parsování modifikátoru
$mod = new Modifier($modifierText);

// Aplikace na element
$mod->decorate($texy, $el);  // Nastaví id, classes, styles, attrs
```

#### Protection Marks

Speciální kontrolní znaky zabraňující re-processingu:

| Mark | Konstanta | Použití |
|------|-----------|---------|
| `\x14` | CONTENT_BLOCK | Block elementy |
| `\x15` | CONTENT_TEXTUAL | Protected text (code) |
| `\x16` | CONTENT_REPLACED | Replaced elementy (img) |
| `\x17` | CONTENT_MARKUP | Inline markup (strong) |

### Reference System

```php
// Definice reference (v textu)
[doc]: https://texy.org "Dokumentace"

// Použití reference
Přečti si "dokumentaci":[doc]

// Interně
$texy->linkModule->addReference('doc', $link);
$link = $texy->linkModule->getReference('doc');
```

### Problémy Legacy Architektury

1. **Těsné provázání** - HtmlElement je parser output i renderer
2. **Okamžité generování** - nelze modifikovat AST před generováním
3. **Složité testování** - nelze testovat AST strukturu
4. **Omezená rozšiřitelnost** - handler chain je nepřehledný
5. **Duplicitní logika** - validace rozptýlena v modulech

---

## Část 2: Cílová AST Architektura (Texy 4.0)

### Základní Princip

Nová architektura odděluje **parsování** (vytváření AST) od **generování** (konverze AST na HTML). Parser vytváří čisté datové struktury (Node objekty), které jsou následně zpracovány a převedeny na HTML.

### Processing Pipeline

```
1. Preprocessing
   └── Normalizace textu
   └── Module.beforeParse() - registrace patterns

2. Parsing → AST
   └── BlockParser/InlineParser hledají syntaxe
   └── Syntax handlery vytváří Node objekty
   └── Výsledek: DocumentNode obsahující strom nodes

3. AST Compiler Passes (afterParse)
   └── HeadingModule - TOC generace, level balancing
   └── ImageModule - reference resolution
   └── LinkModule - reference resolution
   └── Validator - validace tagů/atributů

4. HTML Generation
   └── Texy\Output\Html\Generator traversuje AST
   └── Pro každý node volá registrovaný handler
   └── Handler vrací HTML string

5. HTML Formatting
   └── Texy\Output\Html\Formatter - odsazení, zalamování
   └── unfreezeSpaces(), unProtect()
```

### Klíčové Komponenty

#### AST Node Hierarchy

```
Node (abstract)
├── BlockNode (abstract)
│   ├── DocumentNode          - kořen dokumentu
│   ├── ParagraphNode         - odstavec
│   ├── HeadingNode           - nadpis
│   ├── BlockQuoteNode        - citace
│   ├── ListNode              - seznam
│   ├── ListItemNode          - položka seznamu
│   ├── DefinitionListNode    - definiční seznam
│   ├── DefinitionItemNode    - položka definičního seznamu
│   ├── TableNode             - tabulka
│   ├── TableRowNode          - řádek tabulky
│   ├── TableCellNode         - buňka tabulky
│   ├── CodeBlockNode         - blok kódu
│   ├── FigureNode            - obrázek s popiskem
│   ├── SectionNode           - sekce/div
│   ├── HorizontalRuleNode    - horizontální čára
│   └── CommentNode           - komentář
│
└── InlineNode (abstract)
    ├── TextNode              - prostý text
    ├── RawTextNode           - protected text
    ├── PhraseNode            - formátování (bold, italic)
    ├── LinkNode              - odkaz
    ├── LinkReferenceNode     - reference na odkaz
    ├── LinkDefinitionNode    - definice odkazu
    ├── ImageNode             - obrázek
    ├── ImageDefinitionNode   - definice obrázku
    ├── UrlNode               - přímá URL
    ├── EmailNode             - email
    ├── EmoticonNode          - emotikon
    ├── HtmlTagNode           - HTML tag
    ├── HtmlCommentNode       - HTML komentář
    ├── LineBreakNode         - zalomení řádku
    ├── AnnotationNode        - anotace/acronym
    └── DirectiveNode         - direktiva/script
```

#### Texy\Output\Html\Generator (297 řádků)

Čistý serializér AST → HTML:

```php
class Generator
{
    // Registrace handleru pro typ node
    public function registerHandler(\Closure $handler): void;

    // Dispatch na handler podle typu node
    public function generateNode(Node $node): string;

    // Generování obsahu
    public function generateInlineContent(array $content): string;
    public function generateBlockContent(array $content): string;

    // Utility
    public function generateAttrs(array $attrs): string;
    public function generateModifierAttrs(?Modifier $mod): string;
}
```

#### Module Pattern (Nový)

```php
class HeadingModule extends Module
{
    public function __construct(Texy $texy)
    {
        // Registrace HTML generátoru
        $texy->htmlGenerator->registerHandler($this->solve(...));
    }

    // Registrace patterns (voláno před parsováním)
    public function beforeParse(string &$text): void
    {
        $this->texy->registerBlockPattern(
            $this->parseUnderline(...),
            $pattern,
            'heading/underlined'
        );
    }

    // Syntax handler - vrací AST node
    public function parseUnderline(
        BlockParser $parser,
        array $matches,
        string $name,
        array $offsets,
    ): HeadingNode
    {
        return new HeadingNode(
            content: $this->texy->createInlineParser()->parse($content),
            level: $level,
            type: HeadingNode::Underlined,
            modifier: Modifier::parse($mMod),
            position: new Position($offsets[0], strlen($matches[0])),
        );
    }

    // AST compiler pass
    public function afterParse(DocumentNode $document): void
    {
        $headings = $this->collectHeadings($document);
        // Balancing, TOC generation, ID assignment
    }

    // HTML generator handler
    public function solve(HeadingNode $node, Generator $generator): string
    {
        $attrs = $generator->generateModifierAttrs($node->modifier);
        $content = $generator->generateInlineContent($node->content);
        return $this->texy->protect(
            "<h{$node->level}{$attrs}>{$content}</h{$node->level}>",
            Texy::CONTENT_BLOCK
        );
    }
}
```

#### Texy\Output\Html\Validator

Validace HTML tagů a atributů na úrovni AST:

```php
class Validator
{
    public function processBlockContent(array &$content): void;
    public function processInlineContent(array &$content): void;

    private function validateTag(HtmlTagNode $node): ?Node;
    private function applyAttrs(HtmlTagNode $node): void;
    private function applyClasses(HtmlTagNode $node): void;
    private function applyStyles(HtmlTagNode $node): void;
}
```

### Výhody Nové Architektury

1. **Separace concerns** - parsování ≠ generování
2. **Testovatelnost** - AST lze inspektovat nezávisle na HTML
3. **Rozšiřitelnost** - snadno přidávat compiler passes
4. **Position tracking** - každý node zná svou pozici v source
5. **Centralizovaná validace** - Validator
6. **Immutable generation** - Generator je stateless

---

## Část 3: Momentální Stav Přechodu

### ✅ Hotovo

#### Core Infrastructure
- [x] AST Node hierarchy (34 tříd)
- [x] `Texy\Output\Html\Generator` - základní serializace
- [x] `Texy\Output\Html\Formatter` - formátování výstupu
- [x] `Texy\Output\Html\Validator` - základní validace
- [x] `Position` class - source tracking
- [x] `NodeTraverser` - AST traversal utility
- [x] `Modifier::parse()` - static factory method

#### Moduly (základní refaktoring)
- [x] `HeadingModule` - včetně afterParse pro TOC
- [x] `ListModule` - plně funkční
- [x] `BlockQuoteModule` - základní
- [x] `EmoticonModule` - kompletní
- [x] `HorizLineModule` - základní
- [x] `TypographyModule` - kompletní (post-line)
- [x] `LongWordsModule` - kompletní (post-line)
- [x] `ScriptModule` - základní

### 🔴 Nefunkční / Chybí

#### Reference System
- [ ] `ImageModule::afterParse()` - resolution image references
- [ ] `LinkModule::resolveReferences()` - nefunguje správně
- [ ] Definition nodes se nepropagují správně

#### TableModule - Kriticky Neúplný
- [ ] `processRow()` - zpracování řádků s rowSpan
- [ ] `processCell()` - zpracování buněk s colSpan
- [ ] `finishPart()` - parsování obsahu buněk
- [ ] `$disableTables` - prevence rekurzivních tabulek
- [ ] Caption parsing
- [ ] thead/tbody sekce

#### HtmlModule - Chybí Bezpečnostní Vrstva
- [ ] `validateAttrs()` - validace atributů
- [ ] `applyAttrs()` - filtrování atributů
- [ ] `applyClasses()` - filtrování tříd
- [ ] `applyStyles()` - filtrování stylů
- [ ] URL validace pro src/href
- [ ] Summary building (`$texy->summary`)

#### BlockModule
- [ ] `beforeBlockParse()` - auto-close exclusive blocks
- [ ] `block/texy` typ

#### ParagraphModule
- [ ] `process()` metoda - rozdělení na odstavce
- [ ] `mergeLines` check

#### ImageModule
- [ ] `detectDimensions()` - auto-detekce rozměrů

---

## Část 4: Plán Dokončení

### Fáze 1: AfterParse Compiler Passes

**Cíl:** Funkční reference system pro obrázky a odkazy

1. **ImageModule::afterParse()**
   - Traverse AST, najít `ImageReferenceNode`
   - Resolve proti `$this->references`
   - Nahradit za `ImageNode` s kompletními daty

2. **LinkModule::afterParse()** (oprava)
   - Traverse AST, najít `LinkReferenceNode`
   - Resolve proti `$this->references`
   - Nahradit za `LinkNode` s kompletními daty

### Fáze 2: Nové Node Typy

**Cíl:** Čistší AST struktura

1. **ModifierNode** (nahradí Modifier v AST)
   ```php
   class ModifierNode extends Node
   {
       public ?string $id;
       public array $classes;
       public array $styles;
       public array $attrs;
       public ?string $hAlign;
       public ?string $vAlign;
       public ?string $title;
   }
   ```

2. **InlineContentNode** (wrapper pro inline obsah)
   ```php
   class InlineContentNode extends InlineNode
   {
       /** @var array<InlineNode> */
       public array $children;
   }
   ```

3. **BlockContentNode** (wrapper pro block obsah)
   ```php
   class BlockContentNode extends BlockNode
   {
       /** @var array<BlockNode> */
       public array $children;
   }
   ```

4. **Aktualizace Generator**
   ```php
   // Staré
   public function generateInlineContent(array $content): string;

   // Nové
   public function generateInlineContent(InlineContentNode $content): string;
   ```

### Fáze 3: Validace

1. **Texy\Output\Html\Validator rozšíření**
   - Kontrola `HtmlTagNode` (existující)
   - Kontrola `ModifierNode` (nová)
   - Filtrování tříd, stylů, atributů

### Fáze 4: Refaktoring Modulů

1. **ImageModule**
   - Přesunout `buildImageTag()` zpět z Generator
   - Implementovat `detectDimensions()` (volitelně)

2. **TableModule**
   - Implementovat rowSpan/colSpan logiku
   - Parsování obsahu buněk
   - Caption support

3. **BlockModule**
   - `beforeBlockParse()` auto-close

4. **ParagraphModule**
   - `process()` metoda

### Fáze 5: Cleanup

1. Odstranit deprecated metody z `Modifier`
2. Odstranit `HandlerInvocation` (pokud již není potřeba)
3. Aktualizovat dokumentaci
4. Archivovat legacy složku

---

## Soubory k Modifikaci

### Core
| Soubor | Změna |
|--------|-------|
| `src/Texy/Nodes/ModifierNode.php` | NOVÝ |
| `src/Texy/Nodes/InlineContentNode.php` | NOVÝ |
| `src/Texy/Nodes/BlockContentNode.php` | NOVÝ |
| `src/Texy/Output/Html/Generator.php` | Aktualizace metod |
| `src/Texy/Output/Html/Validator.php` | Rozšíření |

### Moduly
| Soubor | Změna |
|--------|-------|
| `src/Texy/Modules/ImageModule.php` | afterParse(), buildImageTag() |
| `src/Texy/Modules/LinkModule.php` | oprava resolveReferences() |
| `src/Texy/Modules/TableModule.php` | rowSpan/colSpan, cell parsing |
| `src/Texy/Modules/BlockModule.php` | beforeBlockParse() |
| `src/Texy/Modules/ParagraphModule.php` | process() |

### Legacy Reference
| Soubor | Účel |
|--------|------|
| `src/Texy/Modules/legacy/*.php` | Původní verze modulů pro potřeby náhledu |
| `src/Texy/Modules/legacy/TableModule.php` | Reference pro rowSpan/colSpan |
| `src/Texy/Modules/legacy/HtmlModule.php` | Reference pro validaci |
| `src/Texy/Modules/legacy/ImageModule.php` | Reference pro detectDimensions |
| `src/Texy/Modules/legacy/LinkModule.php` | 
---

## Verifikace

Po každé změně:

```bash
# Všechny testy
composer run tester

# Statická analýza
composer run phpstan

# Konkrétní test
vendor/bin/tester tests/Texy/tables.phpt -s
```

### Klíčové Test Soubory
- `tests/Texy/tables.phpt` - tabulky
- `tests/Texy/links.phpt` - odkazy a reference
- `tests/Texy/images.phpt` - obrázky a reference
- `tests/Texy/blocks.phpt` - speciální bloky
- `tests/Texy/paragraphs.phpt` - odstavce

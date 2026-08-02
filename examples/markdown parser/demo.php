<?php declare(strict_types=1);

/**
 * A MARKDOWN PARSER ON THE TEXY ENGINE
 *
 * Texy\Parsing\Engine knows nothing about the Texy language, so a different
 * markup language can be built on it. The resulting AST is the same
 * Texy\Nodes\* tree, so everything that works on the tree works here too.
 *
 * Run:  php "markdown parser/demo.php"
 */

use Texy\Examples\Markdown\HtmlRenderer;
use Texy\Examples\Markdown\Markdown;

if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}

require __DIR__ . '/nodes.php';
require __DIR__ . '/Markdown.php';
require __DIR__ . '/HtmlRenderer.php';


$source = <<<'XX'
	Markdown on the Texy Engine
	===========================

	A paragraph with *emphasis*, **strong** and `code`, plus a [link][home]
	and an ![image](image.png "a title").

	> A quote with a list in it:
	>
	> 1. first
	> 2. second
	>    - nested

	```php
	echo 'fenced code';
	```

	[home]: https://texy.info "Texy"
	XX;


// 1) parse to an AST of Texy\Nodes\* objects
$markdown = new Markdown;
$document = $markdown->parse($source);

// 2) the AST is an ordinary Texy tree, so NodeHelpers works on it
$headings = Texy\NodeHelpers::find($document, Texy\Nodes\HeadingNode::class);
echo '<!-- headings found: ' . count($headings) . " -->\n";

// 3) render
echo (new HtmlRenderer)->render($document);

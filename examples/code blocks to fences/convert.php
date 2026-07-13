<?php declare(strict_types=1);

/**
 * CONVERTING /--php CODE BLOCKS TO FENCES
 *
 * Texy 3 read a delimited block named after a language as code in that language:
 * /--php ... \--. Texy 4 shows a block of a type it does not know as written, so code
 * in a language is written as a fenced block ```php ... ``` instead. This script
 * converts existing texts, /--code php blocks included.
 *
 * WHAT YOU'LL LEARN:
 * - How to give a /--type block a meaning with config->delimitedBlockHandler
 * - How to find nodes of the syntax tree and their place in the source
 * - How to print a node back as Texy source
 * - How to change the source by the ranges of the tree with Rewriter
 * - How to make sure a conversion keeps the HTML as it was
 *
 * USAGE:
 * php convert.php file.texy ...          converts the files in place
 * php convert.php --check file.texy ...  only reports what it would convert
 * php convert.php                        prints the conversion of sample.texy
 *
 * The exit code is 1 when a file keeps a /-- code block, so --check can guard a CI build.
 */

use Texy\{Modifier, Rewriter};
use Texy\Nodes\{BlockNode, CodeBlockNode, UnknownDelimitedNode};
use Texy\Parsing\ParseContext;

if (@!include __DIR__ . '/../../vendor/autoload.php') { // @ dependencies may not be installed
	die('Install packages using `composer install`');
}


$texy = new Texy\Texy;

// A type Texy does not know that looks like a language name is code in that language,
// as it was in Texy 3. Built-in types and blocks with a parameter (/--foo bar) stay
// as Texy makes them.
$texy->config->delimitedBlockHandler = function (
	ParseContext $context,
	string $type,
	string $content,
	?string $param,
	?Modifier $modifier,
	Closure $next,
): ?BlockNode {
	$node = $next();
	return $node instanceof UnknownDelimitedNode && $param === null && preg_match('~^[a-z][a-z0-9_+#-]*$~', $type)
		? new CodeBlockNode($content, $type, $modifier)
		: $node;
};


$args = array_slice($argv, 1);
$check = in_array('--check', $args, true);
$files = array_diff($args, ['--check']);

if (!$files) { // shows what becomes of the sample and writes nothing
	[$converted] = convertCodeBlocks($texy, Texy\Texy::normalize((string) file_get_contents(__DIR__ . '/sample.texy')));
	echo $converted;
	exit;
}

$remaining = false; // does a file keep a /-- code block?
foreach ($files as $file) {
	// ranges of the tree are offsets in the normalized text, so a converted file is saved
	// normalized: with \n line endings and without trailing whitespace
	$text = Texy\Texy::normalize((string) file_get_contents($file));
	[$converted, $count, $left] = convertCodeBlocks($texy, $text);

	if ($count === 0 && $left === 0) {
		echo "$file: nothing to do\n";
		continue;

	} elseif ($count > 0 && $texy->toHtml($converted) !== $texy->toHtml($text)) {
		// the safety net: a conversion that would change what readers see is not written
		echo "$file: skipped, the HTML would change\n";
		$remaining = true;
		continue;
	}

	if ($count > 0 && !$check) {
		file_put_contents($file, $converted);
	}
	echo "$file: " . ($check ? 'to convert' : 'converted') . ": $count, left alone: $left\n";
	$remaining = $remaining || $check || $left > 0;
}

exit($remaining ? 1 : 0);


/**
 * Replaces the code blocks written as /--php or /--code php with fences. The text must be
 * normalized. Returns the converted text, the number of converted blocks and the number
 * of blocks left alone.
 * @return array{string, int, int}
 */
function convertCodeBlocks(Texy\Texy $texy, string $text): array
{
	$rewriter = new Rewriter($text);
	$printer = new Texy\Output\Texy\Renderer($texy);
	$left = 0;

	foreach ($texy->parse($text)->find(CodeBlockNode::class) as $node) {
		$source = substr($text, $node->range->offset, $node->range->length);
		if (!str_starts_with($source, '/--')) {
			continue; // a fence already
		}

		// A block nested in a list item, a quote or a /--div goes on with lines that carry
		// the prefix its first line has, such as '  ' or '> '. The fence gets the same prefix.
		// A block whose lines do not carry it, such as one starting right after a bullet,
		// is left alone.
		$lines = explode("\n", substr($text, 0, $node->range->offset));
		$prefix = end($lines);
		foreach (array_slice(explode("\n", $source), 1) as $line) {
			if (!str_starts_with($line, $prefix) && $line !== rtrim($prefix)) {
				$left++;
				continue 2;
			}
		}

		$fence = str_replace("\n", "\n" . $prefix, $printer->renderNode($node));
		$rewriter->replace($node->range, preg_replace('~[ \t]+$~m', '', $fence));
	}

	return [$rewriter->getResult(), $rewriter->count(), $left];
}

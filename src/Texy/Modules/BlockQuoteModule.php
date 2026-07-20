<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Nodes\BlockQuoteNode;
use Texy\ParseContext;
use Texy\Range;
use Texy\Syntax;
use function max, strlen;


/**
 * Processes blockquote syntax with nested content.
 */
final class BlockQuoteModule extends Texy\Module
{
	public function __construct(
		private Texy\Texy $texy,
	) {
	}


	public function beforeParse(string &$text): void
	{
		$this->texy->registerBlockPattern(
			$this->parse(...),
			'~^
				(?: ' . Texy\Patterns::MODIFIER_H . '\n)? # modifier (1)
				>                                      # blockquote char
				( [ \t]++ | : )                        # space/tab or colon (2)
				( \S.*+ )                              # content (3)
			$~mUx',
			Syntax::Blockquote,
		);
	}


	/**
	 * Parses blockquote.
	 * @param  array{string, ?string, string, string}  $matches
	 * @param  array{int, ?int, int, int}  $offsets
	 */
	public function parse(ParseContext $context, array $matches, array $offsets): ?BlockQuoteNode
	{
		[, $mMod, $mPrefix, $mContent] = $matches;

		$startOffset = $offsets[0];
		$totalLength = strlen($matches[0]);

		// Collect lines with their absolute offsets
		$lines = [['content' => $mContent, 'offset' => $offsets[3]]];
		$spaces = max(1, strlen($mPrefix));

		while ($context->getBlockParser()->next("~^>(?: | ([ \\t]{1,$spaces} | :) (.*))$~mAx", $nextMatches, $nextOffsets)) {
			// group 0 always participates in a successful match, but next() cannot type that
			$line = $nextMatches[0] ?? throw new \LogicException('Match without group 0.');
			$lineOffset = $nextOffsets[0] ?? throw new \LogicException('Match without group 0.');

			$totalLength += strlen($line) + 1; // +1 for \n

			// Track where this line's content starts in absolute terms
			$lineContentOffset = $nextOffsets[2] ?? $lineOffset + 2; // after "> "
			$lines[] = ['content' => $nextMatches[2] ?? '', 'offset' => $lineContentOffset];
		}

		// Join content for parsing, but track line boundaries
		$content = implode("\n", array_column($lines, 'content'));
		$trimmed = trim($content);

		// Parse nested content
		$parsed = $context->parseBlock($trimmed);
		if (!$parsed->children) {
			return null;
		}

		// Fix positions in parsed content using offset map
		Texy\OffsetMap::fromLines($lines, strlen($content) - strlen(ltrim($content)))
			->applyTo($parsed);

		return new BlockQuoteNode(
			$parsed,
			Texy\Modifier::parse($mMod, $offsets[1] ?? null),
			new Range($startOffset, $totalLength),
		);
	}
}

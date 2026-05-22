<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Parsing;

use JetBrains\PhpStorm\Language;
use Texy\{InvalidArgumentException, Nodes, Regexp};
use Texy\Nodes\BlockNode;
use function is_array, is_string, strlen;


/**
 * Parses block structures (paragraphs, headings, lists, tables, etc.).
 * Trailing newlines are trimmed on entry - they are not part of any line.
 * A handler returns a BlockNode, a ContentNode (sequence to splice; empty
 * consumes silently), or null to refuse the match.
 */
final class BlockParser
{
	private string $text;
	private int $offset;
	private int $baseOffset;
	private ParseContext $context;

	/** text from $gapStart to $gapEnd (the current match) is not yet handed to the gap handler */
	private int $gapStart = 0;
	private int $gapEnd = 0;

	/** @var list<BlockNode>  nodes produced so far by the running parse */
	private array $nodes = [];


	public function __construct(
		/** @var array<string, array{handler: \Closure(ParseContext, array<?string>, array<?int>, string): (BlockNode|Nodes\ContentNode|null), pattern: string}> */
		private array $patterns,
		/** @var \Closure(ParseContext, string, int): list<BlockNode> gap handler receives (context, text, offset) */
		private \Closure $gapHandler,
	) {
	}


	public function parse(ParseContext $context, string $text, int $baseOffset = 0): Nodes\ContentNode
	{
		$this->text = $text = rtrim($text, "\n");
		$this->offset = $this->gapStart = 0;
		$this->baseOffset = $baseOffset;
		$this->context = $context;
		$this->nodes = [];
		$matches = $this->match($text, $baseOffset);
		$matches[] = [strlen($text), null, null, 0, null]; // terminal sentinel
		$cursor = 0;

		do {
			do {
				[$mOffset, $mName, $mMatches, , $mOffsets] = $matches[$cursor];
				$cursor++;
				if ($mName === null || $mOffset >= $this->offset) {
					break;
				}
			} while (true);

			$this->gapEnd = $mOffset;
			if ($mName === null) {
				break; // finito
			}

			assert(is_array($mMatches) && is_string($mMatches[0]) && is_array($mOffsets));
			$this->offset = $mOffset + strlen($mMatches[0]) + 1; // 1 = \n

			// call handler
			$handler = $this->patterns[$mName]['handler'];
			$node = $handler($context, $mMatches, $mOffsets, $mName);

			if ($node === null || $this->offset <= $mOffset) { // handler rejects text, it stays in the gap
				$this->offset = $mOffset;
				continue;
			}

			$this->flushGap();
			$this->gapStart = $this->offset;

			if ($node instanceof Nodes\ContentNode) {
				foreach ($node->children as $child) { // a returned sequence is spliced in
					if (!$child instanceof BlockNode) {
						throw new InvalidArgumentException("Handler of block syntax '$mName' returned a sequence containing " . $child::class . '; only block nodes are allowed.');
					}
					$this->nodes[] = $child;
				}
			} else {
				$this->nodes[] = $node;
			}
		} while (true);

		$this->flushGap();
		return new Nodes\ContentNode($this->nodes);
	}


	/**
	 * Nodes produced so far, gap-handler output included; the last one directly
	 * precedes the current match. For handler decisions; the nodes are live, so a
	 * handler may graft consumed text onto the preceding block.
	 * @return list<BlockNode>
	 */
	public function getNodes(): array
	{
		$this->flushGap();
		return $this->nodes;
	}


	/**
	 * Hands the text in front of the current match to the gap handler.
	 */
	private function flushGap(): void
	{
		$start = $this->gapStart;
		$this->gapStart = $this->gapEnd;
		if ($this->gapEnd > $start) {
			$s = substr($this->text, $start, $this->gapEnd - $start);
			$this->nodes = [...$this->nodes, ...($this->gapHandler)($this->context, $s, $this->baseOffset + $start)];
		}
	}


	/**
	 * Match current line against RE.
	 * If successful, increments current position and returns true.
	 * On success group 0 (the whole match) always participates, but the arrays are
	 * left empty on failure, so the type cannot promise it.
	 * @param  ?array<?string>  $matches
	 * @param-out array<?string> $matches
	 * @param  ?array<?int>  $offsets
	 * @param-out array<?int> $offsets
	 */
	public function next(
		#[Language('RegExp')]
		string $pattern,
		?array &$matches,
		?array &$offsets = null,
	): bool
	{
		if ($this->offset > strlen($this->text)) {
			return false;
		}

		$matches = $offsets = [];
		/** @var ?array<array{string, int}> $m */
		$m = Regexp::match(
			$this->text,
			$pattern . 'Am', // anchored & multiline
			captureOffset: true,
			offset: $this->offset,
		);

		if ($m) {
			$this->offset += strlen($m[0][0]) + 1; // 1 = "\n"
			foreach ($m as $key => $value) {
				$matches[$key] = $value[0];
				$offsets[$key] = $value[1] >= 0 ? $this->baseOffset + $value[1] : null;
			}

			return true;
		}

		return false;
	}


	/**
	 * Moves position back by specified number of lines.
	 */
	public function moveBackward(int $linesCount = 1): void
	{
		while (--$this->offset > 0) {
			if ($this->text[$this->offset - 1] === "\n") {
				$linesCount--;
				if ($linesCount < 1) {
					break;
				}
			}
		}

		$this->offset = max($this->offset, 0);
	}


	/** @return list<array{int, ?string, ?array<?string>, int, ?array<?int>}> */
	private function match(string $text, int $baseOffset = 0): array
	{
		$matches = [];
		$priority = 0;

		foreach ($this->patterns as $name => $pattern) {
			$ms = Regexp::matchAll(
				$text,
				$pattern['pattern'],
				captureOffset: true,
			);

			foreach ($ms as $m) {
				$offset = $m[0][1];
				$offsets = [];
				foreach ($m as $k => $v) {
					$offsets[$k] = $v[1] >= 0 ? $baseOffset + $v[1] : null;
					$m[$k] = $v[0];
				}

				$matches[] = [$offset, $name, $m, $priority, $offsets];
			}

			$priority++;
		}

		usort($matches, fn($a, $b): int => $a[0] <=> $b[0] ?: $a[3] <=> $b[3]);
		return $matches;
	}
}

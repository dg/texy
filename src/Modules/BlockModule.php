<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Helpers, Range, Syntax};
use Texy\Nodes\{
	BlockNode, CodeBlockNode, CommentNode, ContentNode, DivNode, HtmlBlockNode, PreformattedNode, TextBlockNode, TexySourceNode,
};
use Texy\Parsing\{OffsetMap, ParseContext};
use function strlen;


/**
 * Processes special blocks (/-- code, html, text, div, etc.).
 */
final class BlockModule extends Texy\Module
{
	public function __construct(
		private Texy\TexyConfig $config,
	) {
	}


	public function register(Texy\Parsing\Registry $registry): void
	{
		foreach ([
			Syntax::BlockDefault,
			Syntax::BlockPre,
			Syntax::BlockCode,
			Syntax::BlockHtml,
			Syntax::BlockText,
			Syntax::BlockTexySource,
			Syntax::BlockComment,
			Syntax::BlockDiv,
		] as $name) {
			$registry->declare($name);
		}

		foreach ($this->config->blockTypes as $type => $_) {
			$registry->declare('block/' . $type);
		}

		$registry->addBlock(
			Syntax::Blocks,
			'~^
				/--++ \ *+                    # opening tag /--
				(.*)                          # content type (1)
				' . Texy\Patterns::ModifierHAlign . '? # modifier (2)
				$
				((?:                         # content (3)
					\n (?0) |                # recursive nested blocks
					\n.*+                    # or any content
				)*)
				(?:
					\n \\\--.* $ |           # closing tag
					\z                       # or end of input
				)
			~mUix',
			$this->parse(...),
		);

		// ```language
		$registry->addBlock(
			Syntax::CodeFenced,
			'~^
				(`{3,}) \ *+                        # opening fence (1)
				([^\n`]*)                           # info string (2)
				$
				((?: \n (?! \1 `*+ \ *+ $ ) .*+ )*) # content (3): lines that are not a valid closing fence
				(?:
					\n \1 `*+ \ *+ $ |              # closing fence (same length or longer)
					\z                              # or end of input
				)
			~mx',
			$this->parseFenced(...),
		);
	}


	/**
	 * Parses ```language fenced code blocks; content is verbatim.
	 * @param  array{string, string, string, string}  $matches
	 * @param  array{int, int, int, int}  $offsets
	 */
	public function parseFenced(ParseContext $context, array $matches, array $offsets): CodeBlockNode
	{
		[, , $mInfo, $mContent] = $matches;

		// a Texy modifier may trail the info string: ```php .{file: Foo.php}
		$mod = null;
		if ($m = Texy\Regexp::match($mInfo, '~' . Texy\Patterns::Modifier . '$~x', captureOffset: true)) {
			$mod = Texy\Modifier::parse($m[1][0], $offsets[2] + $m[1][1]);
			$mInfo = substr($mInfo, 0, $m[0][1]);
		}

		// first word of the info string is the language (GFM)
		$language = Texy\Regexp::split(trim($mInfo), '~\s+~', limit: 2)[0];

		$code = substr($mContent, 1); // drop the separator "\n"; intentional blank lines stay
		return new CodeBlockNode(
			$code,
			$language === '' ? null : $language,
			$mod,
			new Range($offsets[0], strlen($matches[0])),
			OffsetMap::fromLines(OffsetMap::linesOf($code, $offsets[3] + 1)),
		);
	}


	/**
	 * Parses blocks /--foo
	 * @param  array{string, string, ?string, string}  $matches
	 * @param  array{int, int, ?int, int}  $offsets
	 */
	public function parse(ParseContext $context, array $matches, array $offsets): ?BlockNode
	{
		[, $mParam, $mMod, $mContent] = $matches;

		$mod = Texy\Modifier::parse($mMod, $offsets[2] ?? null);
		$parts = Texy\Regexp::split($mParam, '~\s+~', limit: 2);
		// the opening pattern matches case-insensitively, so /--PRE must mean the same
		// as /--pre; only the type is lowercased, a language name keeps its spelling
		$blocktype = empty($parts[0]) ? Syntax::BlockDefault : 'block/' . Helpers::toLower($parts[0]);
		$param = empty($parts[1]) ? null : $parts[1];

		[$content, $map] = OffsetMap::outdentLines(OffsetMap::linesOf($mContent, $offsets[3]));
		$range = new Range($offsets[0], strlen($matches[0]));

		// a disallowed type stays as source text; /--php and friends are code blocks
		$allowedAs = isset($context->allowedSyntaxes[$blocktype]) ? $blocktype : Syntax::BlockCode;
		if (empty($context->allowedSyntaxes[$allowedAs])) {
			return null;
		}

		if ($parser = $this->config->blockTypes[Helpers::toLower($parts[0])] ?? null) {
			$node = $parser($context, $content, $param, $mod);
			if ($node !== null) {
				foreach ($node->getChildren() as $child) {
					$map->applyTo($child); // the parser read the outdented content
				}
				$node->range ??= $range;
			}
			return $node;
		}

		if ($blocktype === Syntax::BlockCode) {
			return new CodeBlockNode($content, $param, $mod, $range, $map);

		} elseif ($blocktype === Syntax::BlockPre) {
			return new PreformattedNode($content, $this->parseVerbatim($context, $content, $map), $mod, $range, $map, Syntax::BlockPre);

		} elseif ($blocktype === Syntax::BlockDefault) {
			$text = $this->parseVerbatim($context, $content, $map, []);
			return new PreformattedNode($content, $text, $mod, $range, $map, Syntax::BlockDefault);

		} elseif ($blocktype === Syntax::BlockComment) {
			return new CommentNode($content, $range);

		} elseif ($blocktype === Syntax::BlockHtml || $blocktype === Syntax::BlockText) {
			// html/text blocks don't use outdent - preserve original indentation
			$content = trim($mContent, "\n");
			$map = OffsetMap::fromLines(OffsetMap::linesOf($content, $offsets[3] + strspn($mContent, "\n")));
			return $blocktype === Syntax::BlockHtml
				? new HtmlBlockNode($content, $this->parseVerbatim($context, $content, $map), $mod, $range, $map)
				: new TextBlockNode($content, $mod, $range, $map);

		} elseif ($blocktype === Syntax::BlockDiv) {
			[$content, $map] = OffsetMap::outdentLines(OffsetMap::linesOf($mContent, $offsets[3]), firstLine: true);
			if ($content === '') {
				return null;
			}
			$parsed = $context->parseBlock($content);
			$map->applyTo($parsed);
			return new DivNode($parsed, $mod, $range);

		} elseif ($blocktype === Syntax::BlockTexySource) {
			// parsed as Texy, its HTML is displayed as source code
			if ($content === '') {
				return null;
			}
			$parsed = $context->parseBlock($content);
			$map->applyTo($parsed);
			return new TexySourceNode($content, $parsed, $mod, $range, $map);
		}

		// /--php and friends: a code block in that language. Anything that does not look
		// like a language name stays refused and degrades to visible text.
		return Texy\Regexp::match($parts[0], '~^[a-z][a-z0-9_+#-]*$~i')
			? new CodeBlockNode($content, $parts[0], $mod, $range, $map)
			: null;
	}


	/**
	 * Parses the markup of a verbatim block, which knows the given syntaxes only: HTML tags,
	 * comments and entities in html and pre blocks, none in a bare /--.
	 * @param  list<string>  $syntaxes
	 */
	private function parseVerbatim(
		ParseContext $context,
		string $text,
		OffsetMap $map,
		array $syntaxes = [Syntax::HtmlTag, Syntax::HtmlComment, Syntax::HtmlEntity],
	): ContentNode
	{
		$parsed = $context->getInlineParser()
			->withPatterns($syntaxes)
			->parse($context, $text);
		$map->applyTo($parsed);
		return $parsed;
	}
}

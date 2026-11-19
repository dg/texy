<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Modifier, Patterns, Range, Syntax};
use Texy\Nodes\{ContentNode, InlineCodeNode, LinkNode, PhraseNode, RawTextNode, TextNode};
use Texy\Parsing\ParseContext;
use function strlen;


/**
 * Processes inline text formatting (bold, italic, code, etc.).
 */
final class PhraseModule extends Texy\Module
{
	/**
	 * Paired phrases: a delimiter, some content, an optional modifier, the same
	 * delimiter again. They all share one skeleton (see buildPhrasePattern()),
	 * so a row holds the delimiter and what sets the phrase apart:
	 *
	 *   close        the closing delimiter, when it differs
	 *   guard        characters that must not precede either delimiter
	 *   guardAfter   characters that must not follow either delimiter
	 *   link         the phrase can carry a :link suffix
	 *   multiline    the content may span lines
	 *   noSpaces     the content holds no whitespace at all
	 *   noDelimiter  the content holds no character of the delimiter
	 *
	 * beforeOpen and afterClose replace a class of the skeleton.
	 */
	private const Phrases = [
		Syntax::StrongEmphasis => ['***', 'guard' => '\\', 'link' => true, 'multiline' => true],
		Syntax::Strong => ['**', 'guard' => '\\', 'link' => true, 'multiline' => true],
		// : guards the // in http://
		Syntax::Emphasis => ['//', 'guard' => ':', 'link' => true, 'multiline' => true],
		Syntax::EmphasisSingleAsterisk => ['*', 'guard' => '\\', 'link' => true, 'noSpaces' => true],
		Syntax::EmphasisSingleAsterisk2 => [
			'*', 'guard' => '\\', 'link' => true, 'multiline' => true,
			'beforeOpen' => '[^\s.,;:<>()"\'-]',    // must stand at a word boundary, ...
			'afterClose' => '[^\s.,;:<>()"?!\'-]',  // ... hence the inverted classes
		],
		Syntax::Inserted => ['++'],
		// < and > guard the arrows <-- and -->
		Syntax::Deleted => ['--', 'guard' => '<', 'guardAfter' => '>'],
		Syntax::Superscript => ['^^'],
		Syntax::Subscript => ['__'],
		Syntax::SpanQuotes => ['"', 'link' => true, 'multiline' => true, 'noDelimiter' => true],
		Syntax::SpanTilde => ['~', 'link' => true, 'multiline' => true, 'noDelimiter' => true],
		Syntax::Quote => ['>>', 'close' => '<<', 'link' => true],
	];

	/** attributes only a link has, which a phrase written as a link loses with it */
	private const LinkAttrs = [
		'download' => 1,
		'href' => 1,
		'hreflang' => 1,
		'ping' => 1,
		'referrerpolicy' => 1,
		'rel' => 1,
		'target' => 1,
	];

	/** @var array<string, string>  patterns of Phrases, which depend on nothing, built once */
	private static array $patterns = [];


	public function register(Texy\Parsing\Engine $engine): void
	{
		$engine->declare(Syntax::PhraseLink);

		foreach ([Syntax::Inserted, Syntax::Deleted, Syntax::Superscript, Syntax::Subscript] as $name) {
			$engine->declare($name, enabled: false);
		}

		foreach (self::Phrases as $syntax => $spec) {
			$engine->addInline($syntax, self::$patterns[$syntax] ??= self::buildPhrasePattern($spec), $this->parsePhrase(...));
		}

		// m^2 alternative superscript
		$engine->addInline(
			Syntax::SuperscriptShort,
			'~
				(?<= [a-z0-9] )                  # preceded by letter or number
				\^
				( [n0-9+-]{1,4}? )               # 1-4 digits, n, + or - (1)
				(?! [a-z0-9] )                   # not followed by letter or number
			~Uix',
			$this->parseSupSub(...),
		);

		// m_2 alternative subscript
		$engine->addInline(
			Syntax::SubscriptShort,
			'~
				(?<= [a-z] )                     # preceded by letter
				_
				( [n0-9]{1,3} )                  # 1-3 digits or n (1)
				(?! [a-z0-9] )                   # not followed by letter or number
			~Uix',
			$this->parseSupSub(...),
		);

		// acronym/abbr "et al."((and others))
		$engine->addInline(
			Syntax::AbbreviationQuotes,
			'~
				(?<! " )                         # not preceded by "
				"
				(?! \s )                         # not followed by space
				# content (1), then modifier (2), " not preceded by space nor followed by " and explanation (3)
				' . self::buildContentPattern(self::buildStep('\r\n"'), Patterns::Modifier . '? (?<! \s ) " (?! " ) \(\( ( .+ ) \)\)') . '
			~Ux',
			$this->parseAcronym(...),
		);

		// acronym/abbr NATO((North Atlantic Treaty Organisation))
		$engine->addInline(
			Syntax::Abbreviation,
			'~
				(?<! [' . Patterns::Letter . '] )  # not preceded by char
				( [' . Patterns::Letter . ']{2,} ) # at least 2 chars (1)
				()                               # modifier placeholder (2)
				\(\(
				# explanation (3), then ))
				' . self::buildContentPattern('(?: [^\n )]++ | [ )] )', '\)\)') . '
			~Ux',
			$this->parseAcronym(...),
		);

		// ''notexy''
		$engine->addInline(
			Syntax::Raw,
			'~
				(?<! \' )                        # not preceded by quote
				\'\'
				(?! [\s\'] )                     # not followed by space or quote
				# content (1), then quotes not preceded by space or quote and not followed by quote
				' . self::buildContentPattern('(?: [^\r\n\']++ | \' )', '(?<! [\s\'] ) \'\' (?! \' )') . '
			~Ux',
			fn($context, $matches, $offsets) => new RawTextNode($matches[1], new Range($offsets[0], strlen($matches[0]))),
		);

		// `code`
		$engine->addInline(
			Syntax::CodeSpan,
			'~
				`
				# content (1), then modifier (2) and a backtick not preceded by space
				' . self::buildContentPattern(self::buildStep('\r\n`', '`'), Patterns::Modifier . '? (?<! \s ) `', first: '\S') . '
				(?: : (' . Patterns::LinkUrl . ') )??  # optional link (3)
			~Ux',
			$this->parsePhrase(...),
		);

		// ....:LINK
		$engine->addInline(
			Syntax::QuickLink,
			'~
				( [' . Patterns::Letter . '0-9@#$%&.,_-]++ )  # allowed chars (1)
				()                                    # modifier placeholder (2)
				: (?= \[ )                            # followed by :[
				(' . Patterns::LinkUrl . ')           # link (3)
			~Ux',
			$this->parseLink(...),
		);

		// [text |link]
		$engine->addInline(
			Syntax::WikiLink,
			'~
				(?<! \[ )                        # not preceded by [
				\[
				(?! [\s*] )                      # not followed by space or *
				( [^|\r\n\]]++ )                 # text (1)
				\|
				# link (2), then modifier (3) and ] not preceded by space nor followed by ]
				' . self::buildContentPattern(self::buildStep('|\r\n\]'), Patterns::Modifier . '? (?<! \s ) ] (?! ] )', skip: false) . '
			~Ux',
			$this->parseLink(...),
		);

		// [text](link)
		$end = Patterns::Modifier . '? (?<! \s ) ] \( ' . self::buildContentPattern('(?: [^\r )]++ | [ ] )', '\)', skip: false);
		$engine->addInline(
			Syntax::MarkdownLink,
			'~
				(?<! [[.] )                      # not preceded by [ or .
				\[
				(?! [\s*] )                      # not followed by space or *
				# text (1), then modifier (2), ] not preceded by space and the link (3) in parentheses
				' . self::buildContentPattern(self::buildStep('|\r\n\]'), $end, skip: false) . '
			~Ux',
			$this->parseLink(...),
		);

		// \* escaped asterisk
		$engine->addInline(
			Syntax::EscapedAsterisk,
			'~\\\\\*~',
			fn($context, $matches, $offsets) => new TextNode('*', new Range($offsets[0], 2)),
		);
	}


	/**
	 * Builds the pattern of a paired phrase from its delimiter spec:
	 *
	 *   (?<! guard ) open (?! space or char ) content modifier? (?<! space or guard ) close (?! char ) (:link)??
	 *
	 * @param  array<int|string, string|bool>  $spec
	 */
	private static function buildPhrasePattern(array $spec): string
	{
		$open = (string) $spec[0];
		$close = (string) ($spec['close'] ?? $open);
		$char = preg_quote($open[0], '~');
		$closeChar = preg_quote($close[0], '~');
		$guard = preg_quote((string) ($spec['guard'] ?? ''), '~');
		$guardAfter = preg_quote((string) ($spec['guardAfter'] ?? ''), '~');

		$beforeOpen = $spec['beforeOpen'] ?? '[' . $char . $guard . ']';
		$afterClose = $spec['afterClose'] ?? '[' . $closeChar . $guardAfter . ']';
		$lone = empty($spec['noDelimiter']) ? $closeChar : '';
		$step = empty($spec['noSpaces'])
			? self::buildStep((empty($spec['multiline']) ? '\r\n' : '') . $closeChar, $lone)
			: '(?: [^\s' . $closeChar . ']++ | [' . $lone . '] )';
		$end = Patterns::Modifier . '?  # modifier (2)
				(?<! [\s' . $closeChar . $guard . '] ) ' . preg_quote($close, '~') . ' (?! ' . $afterClose . ' )  # closing delimiter';

		return '~
				(?<! ' . $beforeOpen . ' ) ' . preg_quote($open, '~') . ' (?! [\s' . $char . $guardAfter . '] )  # opening delimiter
				' . self::buildContentPattern($step, $end)
			. (empty($spec['link'])
				? ''
				: '
				(?: :(' . Patterns::LinkUrl . ') )??  # optional link (3)')
			. '
			~Ux';
	}


	/**
	 * Builds the content group, which runs by $step to the first place where $end matches, and $end
	 * behind it. The loop is possessive, so a long content takes no stack. With $skip, where it finds
	 * no end, no delimiter it passed over would find one either: (*SKIP) resumes the search behind them.
	 */
	private static function buildContentPattern(string $step, string $end, ?string $first = null, bool $skip = true): string
	{
		return '( ' . ($first ?? $step) . ' (?: (?! (?n: ' . $end . '
				) ) ' . $step . ' )*+ )  # content
				' . ($skip ? '(*SKIP)' : '') . '
				' . $end;
	}


	/**
	 * Builds a step of content that may hold spaces: a run of chars other than $stops, a run of spaces
	 * in which no modifier starts, or a single space or char of $lone.
	 */
	private static function buildStep(string $stops, string $lone = ''): string
	{
		return '(?: [^' . $stops . ' ]++ | [ ]++ (?! [ \t]*+ \. ) | [ ' . $lone . '] )';
	}


	/**
	 * Parses phrase patterns. Phrases without a link part (ins, del, sup, sub) have
	 * only two groups, hence the optional third one.
	 * @param  array{string, string, ?string, 3?: ?string}  $matches
	 * @param  array{int, int, ?int, 3?: ?int}  $offsets
	 */
	private function parsePhrase(
		ParseContext $context,
		array $matches,
		array $offsets,
		string $phrase,
	): PhraseNode|InlineCodeNode|LinkNode|null
	{
		[, $mContent, $mMod, $mLink] = $matches + [3 => null];
		$isSpan = $phrase === Syntax::SpanQuotes || $phrase === Syntax::SpanTilde;
		if ($isSpan && $mLink === null && $mMod === null) {
			return null;
		}

		$range = new Range($offsets[0], strlen($matches[0]));
		$textRange = self::trimRange($mContent, $offsets[1]);
		$isLink = $mLink !== null && !empty($context->allowedSyntaxes[Syntax::PhraseLink]);
		$mod = Modifier::parse($mMod, $offsets[2] ?? null);

		if ($isLink && $isSpan) { // a span with a link is the link itself
			$content = $context->parseInline(trim($mContent), $offsets[1]);

		} else {
			if ($mLink !== null && !$isLink && $mod !== null) {
				$mod->attrs = array_diff_key($mod->attrs, self::LinkAttrs);
			}

			$node = $phrase === Syntax::CodeSpan
				? new InlineCodeNode(trim($mContent), $mod, $range, $textRange) // code content is not parsed recursively
				: new PhraseNode($context->parseInline(trim($mContent), $offsets[1]), Syntax::PhraseTypes[$phrase], $mod, $range, $phrase);
			if (!$isLink) {
				return $node;
			}

			$content = new ContentNode([$node]);
		}

		return new LinkNode(
			url: Texy\Helpers::decodeEntities($mLink),
			content: $content,
			modifier: $isSpan ? $mod : null,
			range: $range,
			syntax: $phrase,
			textRange: $textRange,
			urlRange: new Range($offsets[3] ?? throw new \LogicException('Link without group 3.'), strlen($mLink)),
		);
	}


	/**
	 * Parses superscript/subscript alternative (m^2, H_2O).
	 * @param  array{string, string}  $matches
	 * @param  array{int, int}  $offsets
	 */
	private function parseSupSub(ParseContext $context, array $matches, array $offsets, string $phrase): PhraseNode
	{
		$text = str_replace('-', "\u{2212}", $matches[1]); // &minus;
		return new PhraseNode(
			new ContentNode([new TextNode($text, new Range($offsets[1], strlen($matches[1])))]),
			Syntax::PhraseTypes[$phrase],
			null,
			new Range($offsets[0], strlen($matches[0])),
			$phrase,
		);
	}


	/**
	 * Parses acronym/abbr.
	 * @param  array{string, string, ?string, string}  $matches
	 * @param  array{int, int, ?int, int}  $offsets
	 */
	private function parseAcronym(ParseContext $context, array $matches, array $offsets, string $phrase): PhraseNode
	{
		[, $mContent, $mMod, $mTitle] = $matches;
		$contentOffset = $offsets[1];

		$mod = Modifier::parse($mMod, $offsets[2] ?? null) ?? new Modifier;
		$mod->title = trim(Texy\Helpers::unescapeHtml($mTitle));
		$content = $context->parseInline(trim($mContent), $contentOffset);

		return new PhraseNode($content, Syntax::PhraseTypes[$phrase], $mod, new Range($offsets[0], strlen($matches[0])), $phrase);
	}


	/**
	 * Parses links (phrase/quicklink, phrase/markdown, phrase/wikilink).
	 * Note: These links don't support modifiers - modifier is intentionally ignored.
	 * @param  array{string, string, ?string, ?string}  $matches
	 * @param  array{int, int, ?int, ?int}  $offsets
	 */
	private function parseLink(ParseContext $context, array $matches, array $offsets, string $name): LinkNode|ContentNode
	{
		$content = $context->parseInline(trim($matches[1]), $offsets[1]);
		if (empty($context->allowedSyntaxes[Syntax::PhraseLink])) {
			return $content;
		}

		$group = $name === Syntax::WikiLink ? 2 : 3; // [text|link .modifier] has the link before the modifier
		return new LinkNode(
			Texy\Helpers::decodeEntities(trim((string) $matches[$group])),
			$content,
			null,
			new Range($offsets[0], strlen($matches[0])),
			syntax: $name,
			textRange: self::trimRange($matches[1], $offsets[1]),
			urlRange: self::trimRange((string) $matches[$group], (int) $offsets[$group]),
		);
	}


	/** Range of the text without its surrounding whitespace. */
	private static function trimRange(string $text, int $offset): Range
	{
		return new Range($offset + strlen($text) - strlen(ltrim($text)), strlen(trim($text)));
	}
}

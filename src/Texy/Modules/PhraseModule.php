<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\Compat;
use Texy\Modifier;
use Texy\Nodes\ContentNode;
use Texy\Nodes\LinkNode;
use Texy\Nodes\PhraseNode;
use Texy\Nodes\RawTextNode;
use Texy\Nodes\TextNode;
use Texy\Parsing\ParseContext;
use Texy\Patterns;
use Texy\Range;
use Texy\Syntax;
use function strlen;
use const ENT_NOQUOTES;


/**
 * Processes inline text formatting (bold, italic, code, etc.).
 */
final class PhraseModule extends Texy\Module
{
	/**
	 * Paired phrases: a delimiter, some content, an optional modifier, the same
	 * delimiter again. They all share one skeleton (see buildPhrasePattern()),
	 * so a row only says what the delimiters are and which characters must not
	 * touch them:
	 *
	 *   char        the delimiter character, as it appears inside a class
	 *   guard       characters that must not precede either delimiter
	 *   guardAfter  characters that must not follow either delimiter
	 *   link        the phrase can carry a :link suffix
	 *   multiline   the content may span lines
	 *
	 * The remaining keys are overrides for the places where a syntax deviates
	 * from the skeleton; each one is commented, because the deviations are the
	 * only interesting thing here.
	 */
	private const Phrases = [
		Syntax::StrongEmphasis => ['open' => '\*\*\*', 'char' => '\*', 'guard' => '\\\\', 'link' => true, 'multiline' => true],
		Syntax::Strong => ['open' => '\*\*', 'char' => '\*', 'guard' => '\\\\', 'link' => true, 'multiline' => true],
		// : guards the // in http://
		Syntax::Emphasis => ['open' => '//', 'char' => '/', 'guard' => ':', 'link' => true, 'multiline' => true],
		Syntax::EmphasisSingleAsterisk => [
			'open' => '\*', 'char' => '\*', 'guard' => '\\\\', 'link' => true, 'multiline' => true,
			'content' => '\s\*', 'contentPair' => '\*', // a single * phrase holds no whitespace at all
		],
		Syntax::EmphasisSingleAsterisk2 => [
			'open' => '\*', 'char' => '\*', 'guard' => '\\\\', 'link' => true, 'multiline' => true,
			'beforeOpen' => '[^\s.,;:<>()"\'-]',    // must stand at a word boundary, ...
			'afterClose' => '[^\s.,;:<>()"?!\'-]',  // ... hence the inverted classes
		],
		Syntax::Inserted => ['open' => '\+\+', 'char' => '\+'],
		// < and > guard the arrows <-- and -->
		Syntax::Deleted => ['open' => '--', 'char' => '\-', 'guard' => '<', 'guardAfter' => '>'],
		Syntax::Superscript => ['open' => '\^\^', 'char' => '\^'],
		Syntax::Subscript => ['open' => '__', 'char' => '_'],
		Syntax::SpanQuotes => [
			'open' => '"', 'char' => '"', 'link' => true,
			'afterOpen' => '\s', 'beforeClose' => '\s', // the delimiter may touch itself: ""quoted""
			'content' => '\r "', 'contentPair' => ' ',  // and the content may span lines
		],
		Syntax::SpanTilde => [
			'open' => '\~', 'char' => '\~', 'link' => true,
			'afterOpen' => '\s', 'beforeClose' => '\s',
			'content' => '\r \~', 'contentPair' => ' ',
		],
		Syntax::Quote => ['open' => '>>', 'close' => '<<', 'char' => '>', 'closeChar' => '<', 'link' => true],
	];

	public bool $linksAllowed = true;


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed[Syntax::Inserted] = false;
		$texy->allowed[Syntax::Deleted] = false;
		$texy->allowed[Syntax::Superscript] = false;
		$texy->allowed[Syntax::Subscript] = false;
	}


	public function beforeParse(): void
	{
		$texy = $this->texy;

		foreach (self::Phrases as $syntax => $spec) {
			$texy->addLineSyntax($syntax, self::buildPhrasePattern($spec), $this->parsePhrase(...));
		}

		// m^2 alternative superscript
		$texy->addLineSyntax(
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
		$texy->addLineSyntax(
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
		$texy->addLineSyntax(
			Syntax::AbbreviationQuotes,
			'~
				(?<! " )                         # not preceded by "
				"
				(?! \s )                         # not followed by space
				( (?: [^\r\n "]++ | [ ] )+ )     # content (1)
				' . Patterns::Modifier . '?      # modifier (2)
				(?<! \s )                        # not preceded by space
				"
				(?! " )                          # not followed by "
				\(\(
				( .+ )                           # explanation (3)
				\)\)
			~Ux',
			$this->parseAcronym(...),
		);

		// acronym/abbr NATO((North Atlantic Treaty Organisation))
		$texy->addLineSyntax(
			Syntax::Abbreviation,
			'~
				(?<! [' . Patterns::Letter . '] )  # not preceded by char
				( [' . Patterns::Letter . ']{2,} ) # at least 2 chars (1)
				()                               # modifier placeholder (2)
				\(\(
				( (?: [^\n )]++ | [ )] )+ )      # explanation (3)
				\)\)
			~Ux',
			$this->parseAcronym(...),
		);

		// ''notexy''
		$texy->addLineSyntax(
			Syntax::Raw,
			'~
				(?<! \' )                         # not preceded by quote
				\'\'
				(?! [\s\'] )                      # not followed by space or quote
				( (?: [^\r\n\']++ | \' )+ )       # content (1)
				(?<! [\s\'] )                     # not preceded by space or quote
				\'\'
				(?! \' )                          # not followed by quote
			~Ux',
			$this->parseNoTexy(...),
		);

		// `code`
		$texy->addLineSyntax(
			Syntax::Code,
			'~
				`
				( \S (?: [^\r\n `]++ | [ `] )* )        # content (1)
				' . Patterns::Modifier . '?             # modifier (2)
				(?<! \s )                               # not preceded by space
				`
				(?: : (' . Patterns::LinkUrl . ') )??  # optional link (3)
			~Ux',
			$this->parseCode(...),
		);

		// ....:LINK
		$texy->addLineSyntax(
			Syntax::QuickLink,
			'~
				( [' . Patterns::Letter . '0-9@#$%&.,_-]++ )  # allowed chars (1)
				()                                    # modifier placeholder (2)
				: (?= \[ )                            # followed by :[
				(' . Patterns::LinkUrl . ')          # link (3)
			~Ux',
			$this->parseLink(...),
		);

		// [text |link]
		$texy->addLineSyntax(
			Syntax::WikiLink,
			'~
				(?<! \[ )                        # not preceded by [
				\[
				(?! [\s*] )                      # not followed by space or *
				( [^|\r\n\]]++ )                 # text (1)
				\|
				( (?: [^|\r\n \]]++ | [ ] )+ )   # link (2)
				' . Patterns::Modifier . '?      # modifier (3)
				(?<! \s )                        # not preceded by space
				]
				(?! ] )                          # not followed by ]
			~Ux',
			$this->parseWikilink(...),
		);

		// [text](link)
		$texy->addLineSyntax(
			Syntax::MarkdownLink,
			'~
				(?<! [[.] )                     # not preceded by [ or .
				\[
				(?! [\s*] )                     # not followed by space or *
				( (?: [^|\r\n \]]++ | [ ] )+ )  # text (1)
				' . Patterns::Modifier . '?     # modifier (2)
				(?<! \s )                       # not preceded by space
				]
				\(
				( (?: [^\r )]++ | [ ] )+ )      # link (3)
				\)
			~Ux',
			$this->parseLink(...),
		);

		// \* escaped asterisk
		$texy->addLineSyntax(
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
	 * @param  array<string, string|bool>  $spec
	 */
	private static function buildPhrasePattern(array $spec): string
	{
		$char = (string) $spec['char'];
		$closeChar = (string) ($spec['closeChar'] ?? $char);
		$guard = (string) ($spec['guard'] ?? '');
		$guardAfter = (string) ($spec['guardAfter'] ?? '');

		$beforeOpen = $spec['beforeOpen'] ?? self::charClass($char . $guard);
		$afterOpen = $spec['afterOpen'] ?? self::charClass('\s' . $char . $guardAfter);
		$beforeClose = $spec['beforeClose'] ?? self::charClass('\s' . $closeChar . $guard);
		$afterClose = $spec['afterClose'] ?? self::charClass($closeChar . $guardAfter);
		$content = $spec['content'] ?? (empty($spec['multiline']) ? '\r\n' : '') . ' ' . $closeChar;
		$contentPair = $spec['contentPair'] ?? ' ' . $closeChar;

		return '~
				(?<! ' . $beforeOpen . ' ) ' . $spec['open'] . ' (?! ' . $afterOpen . ' )  # opening delimiter
				( (?: [^' . $content . ']++ | [' . $contentPair . '] )+ )  # content (1)
				' . Patterns::Modifier . '?  # modifier (2)
				(?<! ' . $beforeClose . ' ) ' . ($spec['close'] ?? $spec['open']) . ' (?! ' . $afterClose . ' )  # closing delimiter'
			. (empty($spec['link'])
				? ''
				: '
				(?: :(' . Patterns::LinkUrl . ') )??  # optional link (3)')
			. '
			~U' . (empty($spec['multiline']) ? '' : 's') . 'x';
	}


	/**
	 * Wraps characters in a class, unless a single (possibly escaped) one speaks for itself.
	 */
	private static function charClass(string $chars): string
	{
		return preg_match('#^(\\\.|[^\\\])$#D', $chars)
			? $chars
			: '[' . $chars . ']';
	}


	/**
	 * Parses phrase patterns. Phrases without a link part (ins, del, sup, sub) have
	 * only two groups, hence the optional third one.
	 * @param  array{string, string, ?string, 3?: ?string}  $matches
	 * @param  array{int, int, ?int, 3?: ?int}  $offsets
	 */
	public function parsePhrase(
		ParseContext $context,
		array $matches,
		array $offsets,
		string $phrase,
	): PhraseNode|LinkNode|null
	{
		[, $mContent, $mMod, $mLink] = $matches + [3 => null];
		$range = new Range($offsets[0], strlen($matches[0]));
		$contentOffset = $offsets[1];

		// For phrase/span and phrase/span-alt, URL makes it a link
		if ($phrase === Syntax::SpanQuotes || $phrase === Syntax::SpanTilde) {
			if ($mLink !== null) {
				$content = $context->parseInline(trim($mContent), $contentOffset);
				return new LinkNode(Texy\Helpers::removeSoftHyphens($mLink), $content, Modifier::parse($mMod, $offsets[2] ?? null), $range);

			} elseif ($mMod === null) {
				return null;
			}
		}

		$mod = Modifier::parse($mMod, $offsets[2] ?? null);
		$content = $context->parseInline(trim($mContent), $contentOffset);

		// Other phrases with link
		if ($mLink !== null) {
			// Wrap phrase in link
			$phraseNode = new PhraseNode($content, $phrase, $mod, $range);
			return new LinkNode(Texy\Helpers::removeSoftHyphens($mLink), new ContentNode([$phraseNode]), null, $range);
		}

		return new PhraseNode($content, $phrase, $mod, $range);
	}


	/**
	 * Parses code phrase.
	 * @param  array{string, string, ?string, ?string}  $matches
	 * @param  array{int, int, ?int, ?int}  $offsets
	 */
	public function parseCode(ParseContext $context, array $matches, array $offsets, string $phrase): PhraseNode
	{
		[, $mContent, $mMod] = $matches;
		$contentOffset = $offsets[1];
		// Code content is not parsed recursively
		$content = [new TextNode($mContent, new Range($contentOffset, strlen($mContent)))];
		return new PhraseNode(
			new ContentNode($content),
			$phrase,
			Modifier::parse($mMod, $offsets[2] ?? null),
			new Range($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Parses superscript/subscript alternative (m^2, H_2O).
	 * @param  array{string, string}  $matches
	 * @param  array{int, int}  $offsets
	 */
	public function parseSupSub(ParseContext $context, array $matches, array $offsets, string $phrase): PhraseNode
	{
		[, $mContent] = $matches;
		$mContent = str_replace('-', "\u{2212}", $mContent); // &minus;
		$content = [new TextNode(Texy\Helpers::decodeEntities($mContent), new Range($offsets[1], strlen($matches[1])))];
		return new PhraseNode(
			new ContentNode($content),
			$phrase,
			null,
			new Range($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Parses acronym/abbr.
	 * @param  array{string, string, ?string, string}  $matches
	 * @param  array{int, int, ?int, int}  $offsets
	 */
	public function parseAcronym(ParseContext $context, array $matches, array $offsets, string $phrase): PhraseNode
	{
		[, $mContent, $mMod, $mTitle] = $matches;
		$contentOffset = $offsets[1];

		$mod = Modifier::parse($mMod, $offsets[2] ?? null) ?? new Modifier;
		$mod->title = trim(Texy\Helpers::unescapeHtml($mTitle));
		$content = [new TextNode(Texy\Helpers::decodeEntities(trim($mContent)), new Range($contentOffset, strlen(trim($mContent))))];

		return new PhraseNode(new ContentNode($content), $phrase, $mod, new Range($offsets[0], strlen($matches[0])));
	}


	/**
	 * Parses ''notexy''.
	 * @param  array{string, string}  $matches
	 * @param  array{int, int}  $offsets
	 */
	public function parseNoTexy(ParseContext $context, array $matches, array $offsets): RawTextNode
	{
		[, $mContent] = $matches;
		return new RawTextNode($mContent, new Range($offsets[0], strlen($matches[0])));
	}


	/**
	 * Parses links (phrase/quicklink, phrase/markdown).
	 * Note: Markdown links don't support modifiers - modifier is intentionally ignored.
	 * @param  array{string, string, ?string, string}  $matches
	 * @param  array{int, int, ?int, int}  $offsets
	 */
	public function parseLink(ParseContext $context, array $matches, array $offsets): LinkNode
	{
		[, $mContent, $mMod, $mLink] = $matches;
		return new LinkNode(
			Texy\Helpers::removeSoftHyphens(trim($mLink)),
			$context->parseInline(trim($mContent), $offsets[1]),
			null,  // quicklink and markdown links don't support modifiers
			new Range($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Parses wikilink [text|link].
	 * Note: Wikilinks don't support modifiers - modifier is intentionally ignored.
	 * @param  array{string, string, string, ?string}  $matches
	 * @param  array{int, int, int, ?int}  $offsets
	 */
	public function parseWikilink(ParseContext $context, array $matches, array $offsets): LinkNode
	{
		[, $mContent, $mLink] = $matches;
		return new LinkNode(
			Texy\Helpers::removeSoftHyphens(trim($mLink)),
			$context->parseInline(trim($mContent), $offsets[1]),
			null,  // wikilinks don't support modifiers
			new Range($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * @deprecated use $texy->htmlOutput->phraseTags etc. instead
	 */
	public function &__get(string $name): mixed
	{
		return Compat\Legacy::ref($this->texy, Compat\Legacy::OfModule['phraseModule'], '$texy->phraseModule', $name, 'read');
	}


	/**
	 * @deprecated use $texy->htmlOutput->phraseTags etc. instead
	 */
	public function __set(string $name, mixed $value): void
	{
		Compat\Legacy::set($this->texy, Compat\Legacy::OfModule['phraseModule'], '$texy->phraseModule', $name, $value);
	}


	public function __isset(string $name): bool
	{
		return Compat\Legacy::isSet($this->texy, Compat\Legacy::OfModule['phraseModule'], $name);
	}
}

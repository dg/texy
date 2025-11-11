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
	public function __construct(
		private Texy\TexyConfig $config,
	) {
	}


	public function register(Texy\Parsing\Registry $registry): void
	{
		foreach ([Syntax::Inserted, Syntax::Deleted, Syntax::Superscript, Syntax::Subscript] as $name) {
			$registry->declare($name, enabled: false);
		}

		// ***strong+emphasis***
		$registry->addInline(
			Syntax::StrongEmphasis,
			'~
				(?<! [*\\\] )                     # not preceded by * or \
				\*\*\*
				(?! [\s*] )                       # not followed by space or *
				( (?: [^ *]++ | [ *] )+ )         # content (1)
				' . Patterns::MODIFIER . '?       # modifier (2)
				(?<! [\s*\\\] )                   # not preceded by space, * or \
				\*\*\*
				(?! \* )                          # not followed by *
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~Usx',
			$this->parsePhrase(...),
		);

		// **strong**
		$registry->addInline(
			Syntax::Strong,
			'~
				(?<! [*\\\] )                     # not preceded by * or \
				\*\*
				(?! [\s*] )                       # not followed by space or *
				( (?: [^ *]++ | [ *] )+ )         # content (1)
				' . Patterns::MODIFIER . '?       # modifier (2)
				(?<! [\s*\\\] )                   # not preceded by space, * or \
				\*\*
				(?! \* )                          # not followed by *
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~Usx',
			$this->parsePhrase(...),
		);

		// //emphasis//
		$registry->addInline(
			Syntax::Emphasis,
			'~
				(?<! [/:] )                       # not preceded by / or :
				//
				(?! [\s/] )                       # not followed by space or /
				( (?: [^ /]++ | [ /] )+ )         # content (1)
				' . Patterns::MODIFIER . '?       # modifier (2)
				(?<! [\s/:] )                     # not preceded by space, / or :
				//
				(?! / )                           # not followed by /
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~Usx',
			$this->parsePhrase(...),
		);

		// *emphasisAlt*
		$registry->addInline(
			Syntax::EmphasisSingleAsterisk,
			'~
				(?<! [*\\\] )                    # not preceded by * or \
				\*
				(?! [\s*] )                      # not followed by space or *
				( (?: [^\s*]++ | [*] )+ )        # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s*\\\] )                  # not preceded by space, * or \
				\*
				(?! \* )                         # not followed by *
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~Usx',
			$this->parsePhrase(...),
		);

		// *emphasisAlt2*
		$registry->addInline(
			Syntax::EmphasisSingleAsterisk2,
			'~
				(?<! [^\s.,;:<>()"\'-] )         # must be preceded by these chars
				\*
				(?! [\s*] )                      # not followed by space or *
				( (?: [^ *]++ | [ *] )+ )        # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s*\\\] )                  # not preceded by space, * or \
				\*
				(?! [^\s.,;:<>()"?!\'-] )        # must be followed by these chars
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~Usx',
			$this->parsePhrase(...),
		);

		// ++inserted++
		$registry->addInline(
			Syntax::Inserted,
			'~
				(?<! \+ )                        # not preceded by +
				\+\+
				(?! [\s+] )                      # not followed by space or +
				( (?: [^\r\n +]++ | [ +] )+ )    # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s+] )                     # not preceded by space or +
				\+\+
				(?! \+ )                         # not followed by +
			~Ux',
			$this->parsePhrase(...),
		);

		// --deleted--
		$registry->addInline(
			Syntax::Deleted,
			'~
				(?<! [<-] )                      # not preceded by < or -
				--
				(?! [\s>-] )                     # not followed by space, > or -
				( (?: [^\r\n -]++ | [ -] )+ )    # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s<-] )                    # not preceded by space, < or -
				--
				(?! [>-] )                       # not followed by > or -
			~Ux',
			$this->parsePhrase(...),
		);

		// ^^superscript^^
		$registry->addInline(
			Syntax::Superscript,
			'~
				(?<! \^ )                        # not preceded by ^
				\^\^
				(?! [\s^] )                      # not followed by space or ^
				( (?: [^\r\n ^]++ | [ ^] )+ )    # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s^] )                     # not preceded by space or ^
				\^\^
				(?! \^ )                         # not followed by ^
			~Ux',
			$this->parsePhrase(...),
		);

		// __subscript__
		$registry->addInline(
			Syntax::Subscript,
			'~
				(?<! _ )                         # not preceded by _
				__
				(?! [\s_] )                      # not followed by space or _
				( (?: [^\r\n _]++ | [ _] )+ )    # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s_] )                     # not preceded by space or _
				__
				(?! _ )                          # not followed by _
			~Ux',
			$this->parsePhrase(...),
		);

		// "span"
		$registry->addInline(
			Syntax::SpanQuotes,
			'~
				(?<! " )                         # not preceded by "
				"
				(?! \s )                         # not followed by space
				( (?: [^\r "]++ | [ ] )+ )       # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! \s )                        # not preceded by space
				"
				(?! " )                          # not followed by "
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~Ux',
			$this->parsePhrase(...),
		);

		// ~alternative span~
		$registry->addInline(
			Syntax::SpanTilde,
			'~
				(?<! \~ )
				\~
				(?! \s )                         # not followed by space
				( (?: [^\r \~]++ | [ ] )+ )      # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! \s )                        # not preceded by space
				\~
				(?! \~ )
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~Ux',
			$this->parsePhrase(...),
		);

		// >>quote<<
		$registry->addInline(
			Syntax::Quote,
			'~
				(?<! > )                         # not preceded by >
				>>
				(?! [\s>] )                      # not followed by space or >
				( (?: [^\r\n <]++ | [ <] )+ )    # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s<] )                     # not preceded by space or <
				<<
				(?! < )                          # not followed by <
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~Ux',
			$this->parsePhrase(...),
		);

		// m^2 alternative superscript
		$registry->addInline(
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
		$registry->addInline(
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
		$registry->addInline(
			Syntax::AbbreviationQuotes,
			'~
				(?<! " )                         # not preceded by "
				"
				(?! \s )                         # not followed by space
				( (?: [^\r\n "]++ | [ ] )+ )     # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
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
		$registry->addInline(
			Syntax::Abbreviation,
			'~
				(?<! [' . Patterns::CHAR . '] )  # not preceded by char
				( [' . Patterns::CHAR . ']{2,} ) # at least 2 chars (1)
				()                               # modifier placeholder (2)
				\(\(
				( (?: [^\n )]++ | [ )] )+ )      # explanation (3)
				\)\)
			~Ux',
			$this->parseAcronym(...),
		);

		// ''notexy''
		$registry->addInline(
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
		$registry->addInline(
			Syntax::Code,
			'~
				`
				( \S (?: [^\r\n `]++ | [ `] )* )        # content (1)
				' . Patterns::MODIFIER . '?             # modifier (2)
				(?<! \s )                               # not preceded by space
				`
				(?: : (' . Patterns::LINK_URL . ') )??  # optional link (3)
			~Ux',
			$this->parsePhrase(...),
		);

		// ....:LINK
		$registry->addInline(
			Syntax::QuickLink,
			'~
				( [' . Patterns::CHAR . '0-9@#$%&.,_-]++ )  # allowed chars (1)
				()                                    # modifier placeholder (2)
				: (?= \[ )                            # followed by :[
				(' . Patterns::LINK_URL . ')          # link (3)
			~Ux',
			$this->parseLink(...),
		);

		// [text |link]
		$registry->addInline(
			Syntax::WikiLink,
			'~
				(?<! \[ )                        # not preceded by [
				\[
				(?! [\s*] )                      # not followed by space or *
				( [^|\r\n\]]++ )                 # text (1)
				\|
				( (?: [^|\r\n \]]++ | [ ] )+ )   # link (2)
				' . Patterns::MODIFIER . '?      # modifier (3)
				(?<! \s )                        # not preceded by space
				]
				(?! ] )                          # not followed by ]
			~Ux',
			$this->parseWikilink(...),
		);

		// [text](link)
		$registry->addInline(
			Syntax::MarkdownLink,
			'~
				(?<! [[.] )                     # not preceded by [ or .
				\[
				(?! [\s*] )                     # not followed by space or *
				( (?: [^|\r\n \]]++ | [ ] )+ )  # text (1)
				' . Patterns::MODIFIER . '?     # modifier (2)
				(?<! \s )                       # not preceded by space
				]
				\(
				( (?: [^\r )]++ | [ ] )+ )      # link (3)
				\)
			~Ux',
			$this->parseLink(...),
		);

		// \* escaped asterisk
		$registry->addInline(
			Syntax::EscapedAsterisk,
			'~\\\\\*~',
			fn($context, $matches, $offsets) => new TextNode('*', new Range($offsets[0], 2)),
		);
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
	): PhraseNode|InlineCodeNode|LinkNode|null
	{
		[, $mContent, $mMod, $mLink] = $matches + [3 => null];
		$range = new Range($offsets[0], strlen($matches[0]));
		$contentOffset = $offsets[1];
		$textRange = self::trimRange($mContent, $contentOffset);
		$urlRange = $mLink === null ? null : new Range($offsets[3] ?? throw new \LogicException('Link without group 3.'), strlen($mLink));

		// For phrase/span and phrase/span-alt, URL makes it a link
		if ($phrase === Syntax::SpanQuotes || $phrase === Syntax::SpanTilde) {
			if ($mLink !== null) {
				return new LinkNode(
					url: Texy\Helpers::decodeEntities($mLink),
					content: $context->parseInline(trim($mContent), $contentOffset),
					modifier: Modifier::parse($mMod, $offsets[2] ?? null),
					range: $range,
					syntax: $phrase,
					textRange: $textRange,
					urlRange: $urlRange,
				);

			} elseif ($mMod === null) {
				return null;
			}
		}

		$mod = Modifier::parse($mMod, $offsets[2] ?? null);
		$node = $phrase === Syntax::Code
			? new InlineCodeNode(trim($mContent), $mod, $range, $textRange) // code content is not parsed recursively
			: new PhraseNode($context->parseInline(trim($mContent), $contentOffset), Syntax::PhraseTypes[$phrase], $mod, $range, $phrase);

		// Other phrases with link
		if ($mLink !== null && $this->config->phraseLinks) {
			return new LinkNode(
				url: Texy\Helpers::decodeEntities($mLink),
				content: new ContentNode([$node]),
				range: $range,
				syntax: $phrase,
				textRange: $textRange,
				urlRange: $urlRange,
			);
		}

		return $node;
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
	public function parseAcronym(ParseContext $context, array $matches, array $offsets, string $phrase): PhraseNode
	{
		[, $mContent, $mMod, $mTitle] = $matches;
		$contentOffset = $offsets[1];

		$mod = Modifier::parse($mMod, $offsets[2] ?? null) ?? new Modifier;
		$mod->title = trim(Texy\Helpers::unescapeHtml($mTitle));
		$content = $context->parseInline(trim($mContent), $contentOffset);

		return new PhraseNode($content, Syntax::PhraseTypes[$phrase], $mod, new Range($offsets[0], strlen($matches[0])), $phrase);
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
	public function parseLink(ParseContext $context, array $matches, array $offsets, string $name): LinkNode
	{
		[, $mContent, $mMod, $mLink] = $matches;
		return new LinkNode(
			Texy\Helpers::decodeEntities(trim($mLink)),
			$context->parseInline(trim($mContent), $offsets[1]),
			null,  // quicklink and markdown links don't support modifiers
			new Range($offsets[0], strlen($matches[0])),
			syntax: $name,
			textRange: self::trimRange($mContent, $offsets[1]),
			urlRange: self::trimRange($mLink, $offsets[3]),
		);
	}


	/**
	 * Parses wikilink [text|link].
	 * Note: Wikilinks don't support modifiers - modifier is intentionally ignored.
	 * @param  array{string, string, string, ?string}  $matches
	 * @param  array{int, int, int, ?int}  $offsets
	 */
	public function parseWikilink(ParseContext $context, array $matches, array $offsets, string $name): LinkNode
	{
		[, $mContent, $mLink] = $matches;
		return new LinkNode(
			Texy\Helpers::decodeEntities(trim($mLink)),
			$context->parseInline(trim($mContent), $offsets[1]),
			null,  // wikilinks don't support modifiers
			new Range($offsets[0], strlen($matches[0])),
			syntax: $name,
			textRange: self::trimRange($mContent, $offsets[1]),
			urlRange: self::trimRange($mLink, $offsets[2]),
		);
	}


	/** Range of the text without its surrounding whitespace. */
	private static function trimRange(string $text, int $offset): Range
	{
		return new Range($offset + strlen($text) - strlen(ltrim($text)), strlen(trim($text)));
	}
}

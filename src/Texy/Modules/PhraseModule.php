<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Modifier;
use Texy\Nodes\ContentNode;
use Texy\Nodes\LinkNode;
use Texy\Nodes\PhraseNode;
use Texy\Nodes\RawTextNode;
use Texy\Nodes\TextNode;
use Texy\ParseContext;
use Texy\Patterns;
use Texy\Position;
use Texy\Syntax;
use function str_replace, strlen, trim;


/**
 * Processes inline text formatting (bold, italic, code, etc.).
 */
final class PhraseModule extends Texy\Module
{
	public bool $linksAllowed = true;


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed[Syntax::Inserted] = false;
		$texy->allowed[Syntax::Deleted] = false;
		$texy->allowed[Syntax::Superscript] = false;
		$texy->allowed[Syntax::Subscript] = false;
	}


	public function beforeParse(string &$text): void
	{
		$texy = $this->texy;
		/*
		// UNIVERSAL
		$texy->registerLinePattern(
			array($this, 'patternPhrase'),
			'~((?>([*+/^_"\~`-])+?))(?!\s)(.*(?!\2).)'.Texy\Patterns::MODIFIER.'?(?<!\s)\1(?!\2)(?::('.Texy\Patterns::LINK_URL.'))??~Us',
			'phrase/strong'
		);
		*/

		// ***strong+emphasis***
		$texy->registerLinePattern(
			$this->parsePhrase(...),
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
			~Us',
			Syntax::StrongEmphasis,
		);

		// **strong**
		$texy->registerLinePattern(
			$this->parsePhrase(...),
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
			~Us',
			Syntax::Strong,
		);

		// //emphasis//
		$texy->registerLinePattern(
			$this->parsePhrase(...),
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
			~Us',
			Syntax::Emphasis,
		);

		// *emphasisAlt*
		$texy->registerLinePattern(
			$this->parsePhrase(...),
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
			~Us',
			Syntax::EmphasisSingleAsterisk,
		);

		// *emphasisAlt2*
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! [^\s.,;:<>()"\'' . Patterns::MARK . '-] )  # must be preceded by these chars
				\*
				(?! [\s*] )                      # not followed by space or *
				( (?: [^ *]++ | [ *] )+ )        # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s*\\\] )                  # not preceded by space, * or \
				\*
				(?! [^\s.,;:<>()"?!\'-] )        # must be followed by these chars
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~Us',
			Syntax::EmphasisSingleAsterisk2,
		);

		// ++inserted++
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! \+ )                        # not preceded by +
				\+\+
				(?! [\s+] )                      # not followed by space or +
				( (?: [^\r\n +]++ | [ +] )+ )    # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s+] )                     # not preceded by space or +
				\+\+
				(?! \+ )                         # not followed by +
			~U',
			Syntax::Inserted,
		);

		// --deleted--
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! [<-] )                      # not preceded by < or -
				--
				(?! [\s>-] )                     # not followed by space, > or -
				( (?: [^\r\n -]++ | [ -] )+ )    # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s<-] )                    # not preceded by space, < or -
				--
				(?! [>-] )                       # not followed by > or -
			~U',
			Syntax::Deleted,
		);

		// ^^superscript^^
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! \^ )                        # not preceded by ^
				\^\^
				(?! [\s^] )                      # not followed by space or ^
				( (?: [^\r\n ^]++ | [ ^] )+ )    # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s^] )                     # not preceded by space or ^
				\^\^
				(?! \^ )                         # not followed by ^
			~U',
			Syntax::Superscript,
		);

		// m^2 alternative superscript
		$texy->registerLinePattern(
			$this->parseSupSub(...),
			'~
				(?<= [a-z0-9] )                  # preceded by letter or number
				\^
				( [n0-9+-]{1,4}? )               # 1-4 digits, n, + or - (1)
				(?! [a-z0-9] )                   # not followed by letter or number
			~Ui',
			Syntax::SuperscriptShort,
		);

		// __subscript__
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! _ )                         # not preceded by _
				__
				(?! [\s_] )                      # not followed by space or _
				( (?: [^\r\n _]++ | [ _] )+ )    # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s_] )                     # not preceded by space or _
				__
				(?! _ )                          # not followed by _
			~U',
			Syntax::Subscript,
		);

		// m_2 alternative subscript
		$texy->registerLinePattern(
			$this->parseSupSub(...),
			'~
				(?<= [a-z] )                     # preceded by letter
				_
				( [n0-9]{1,3} )                  # 1-3 digits or n (1)
				(?! [a-z0-9] )                   # not followed by letter or number
			~Ui',
			Syntax::SubscriptShort,
		);

		// "span"
		$texy->registerLinePattern(
			$this->parsePhrase(...),
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
			~U',
			Syntax::SpanQuotes,
		);

		// ~alternative span~
		$texy->registerLinePattern(
			$this->parsePhrase(...),
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
			~U',
			Syntax::SpanTilde,
		);

		// >>quote<<
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! > )                         # not preceded by >
				>>
				(?! [\s>] )                      # not followed by space or >
				( (?: [^\r\n <]++ | [ <] )+ )    # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s<] )                     # not preceded by space or
				<<
				(?! < )                          # not followed by <
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~U',
			Syntax::Quote,
		);

		// acronym/abbr "et al."((and others))
		$texy->registerLinePattern(
			$this->parseAcronym(...),
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
			~U',
			Syntax::AbbreviationQuotes,
		);

		// acronym/abbr NATO((North Atlantic Treaty Organisation))
		$texy->registerLinePattern(
			$this->parseAcronym(...),
			'~
				(?<! [' . Patterns::CHAR . '] )  # not preceded by char
				( [' . Patterns::CHAR . ']{2,} ) # at least 2 chars (1)
				()                               # modifier placeholder (2)
				\(\(
				( (?: [^\n )]++ | [ )] )+ )      # explanation (3)
				\)\)
			~U',
			Syntax::Abbreviation,
		);

		// ''notexy''
		$texy->registerLinePattern(
			$this->parseNoTexy(...),
			'~
				(?<! \' )                         # not preceded by quote
				\'\'
				(?! [\s\'] )                      # not followed by space or quote
				( (?: [^' . Patterns::MARK . '\r\n\']++ | \' )+ )  # content (1)
				(?<! [\s\'] )                     # not preceded by space or quote
				\'\'
				(?! \' )                          # not followed by quote
			~U',
			Syntax::Raw,
		);

		// `code`
		$texy->registerLinePattern(
			$this->parseCode(...),
			'~
				`
				( \S (?: [^' . Patterns::MARK . '\r\n `]++ | [ `] )* )  # content (1)
				' . Patterns::MODIFIER . '?             # modifier (2)
				(?<! \s )                               # not preceded by space
				`
				(?: : (' . Patterns::LINK_URL . ') )??  # optional link (3)
			~U',
			Syntax::Code,
		);

		// ....:LINK
		$texy->registerLinePattern(
			$this->parseLink(...),
			'~
				( [' . Patterns::CHAR . '0-9@#$%&.,_-]++ )  # allowed chars (1)
				()                                    # modifier placeholder (2)
				: (?= \[ )                            # followed by :[
				(' . Patterns::LINK_URL . ')          # link (3)
			~U',
			Syntax::QuickLink,
		);

		// [text |link]
		$texy->registerLinePattern(
			$this->parseWikilink(...),
			'~
				(?<! \[ )                        # not preceded by [
				\[
				(?! [\s*] )                      # not followed by space or *
				( [^|\r\n\]]++ )                 # text (1)
				\|
				( (?: [^' . Patterns::MARK . '|\r\n \]]++ | [ ] )+ )  # link (2)
				' . Patterns::MODIFIER . '?      # modifier (3)
				(?<! \s )                        # not preceded by space
				]
				(?! ] )                          # not followed by ]
			~U',
			Syntax::WikiLink,
		);

		// [text](link)
		$texy->registerLinePattern(
			$this->parseLink(...),
			'~
				(?<! [[.] )                     # not preceded by [ or .
				\[
				(?! [\s*] )                     # not followed by space or *
				( (?: [^|\r\n \]]++ | [ ] )+ )  # text (1)
				' . Patterns::MODIFIER . '?     # modifier (2)
				(?<! \s )                       # not preceded by space
				]
				\(
				( (?: [^' . Patterns::MARK . '\r )]++ | [ ] )+ )  # link (3)
				\)
			~U',
			Syntax::MarkdownLink,
		);

		// \* escaped asterisk
		$texy->registerLinePattern(
			fn($context, $matches, $offsets) => new TextNode('*', new Position($offsets[0], 2)),
			'~\\\\\*~',
			Syntax::EscapedAsterisk,
		);
	}


	/**
	 * Parses phrase patterns.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parsePhrase(
		ParseContext $context,
		array $matches,
		array $offsets,
		string $phrase,
	): PhraseNode|LinkNode|null
	{
		[, $mContent, $mMod, $mLink] = $matches + [2 => null, 3 => null];
		$position = new Position($offsets[0], strlen($matches[0]));
		$contentOffset = $offsets[1] ?? $offsets[0];

		// For phrase/span and phrase/span-alt, URL makes it a link
		if (($phrase === Syntax::SpanQuotes || $phrase === Syntax::SpanTilde) && $mLink !== null) {
			$content = $context->parseInline(trim($mContent), $contentOffset);
			return new LinkNode($mLink, $content, Modifier::parse($mMod), $position);
		}

		// For phrase/span without URL and without modifier, return null (means "...")
		if (($phrase === Syntax::SpanQuotes || $phrase === Syntax::SpanTilde) && $mMod === null && $mLink === null) {
			return null;
		}

		$mod = Modifier::parse($mMod);
		$content = $context->parseInline(trim($mContent), $contentOffset);

		// Other phrases with link
		if ($mLink !== null) {
			// Wrap phrase in link
			$phraseNode = new PhraseNode($content, $phrase, $mod, $position);
			return new LinkNode($mLink, new ContentNode([$phraseNode]), null, $position);
		}

		return new PhraseNode($content, $phrase, $mod, $position);
	}


	/**
	 * Parses code phrase.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseCode(ParseContext $context, array $matches, array $offsets, string $phrase): PhraseNode
	{
		[, $mContent, $mMod] = $matches + [2 => null];
		$contentOffset = $offsets[1] ?? $offsets[0];
		// Code content is not parsed recursively
		$content = [new TextNode($mContent, new Position($contentOffset, strlen($mContent)))];
		return new PhraseNode(
			new ContentNode($content),
			$phrase,
			Modifier::parse($mMod),
			new Position($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Parses superscript/subscript alternative (m^2, H_2O).
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseSupSub(ParseContext $context, array $matches, array $offsets, string $phrase): PhraseNode
	{
		[, $mContent] = $matches;
		$mContent = str_replace('-', "\u{2212}", $mContent); // &minus;
		$content = [new TextNode($mContent, new Position($offsets[1] ?? $offsets[0], strlen($matches[1])))];
		return new PhraseNode(
			new ContentNode($content),
			$phrase,
			null,
			new Position($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Parses acronym/abbr.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseAcronym(ParseContext $context, array $matches, array $offsets, string $phrase): PhraseNode
	{
		[, $mContent, $mMod, $mTitle] = $matches + [2 => null, 3 => ''];
		$contentOffset = $offsets[1] ?? $offsets[0];

		$mod = Modifier::parse($mMod) ?? new Modifier;
		$mod->title = trim(Texy\Helpers::unescapeHtml($mTitle));
		$content = [new TextNode(trim($mContent), new Position($contentOffset, strlen(trim($mContent))))];

		return new PhraseNode(new ContentNode($content), $phrase, $mod, new Position($offsets[0], strlen($matches[0])));
	}


	/**
	 * Parses ''notexy''.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseNoTexy(ParseContext $context, array $matches, array $offsets): RawTextNode
	{
		[, $mContent] = $matches;
		return new RawTextNode($mContent, new Position($offsets[0], strlen($matches[0])));
	}


	/**
	 * Parses links (phrase/quicklink, phrase/markdown).
	 * Note: Markdown links don't support modifiers - modifier is intentionally ignored.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseLink(ParseContext $context, array $matches, array $offsets): LinkNode
	{
		[, $mContent, $mMod, $mLink] = $matches + [2 => null, 3 => null];
		return new LinkNode(
			trim($mLink ?? ''),
			$context->parseInline(trim($mContent), $offsets[1] ?? $offsets[0]),
			null,  // quicklink and markdown links don't support modifiers
			new Position($offsets[0], strlen($matches[0])),
		);
	}


	/**
	 * Parses wikilink [text|link].
	 * Note: Wikilinks don't support modifiers - modifier is intentionally ignored.
	 * @param  array<?string>  $matches
	 * @param  array<?int>  $offsets
	 */
	public function parseWikilink(ParseContext $context, array $matches, array $offsets): LinkNode
	{
		[, $mContent, $mLink] = $matches;
		return new LinkNode(
			trim($mLink),
			$context->parseInline(trim($mContent), $offsets[1] ?? $offsets[0]),
			null,  // wikilinks don't support modifiers
			new Position($offsets[0], strlen($matches[0])),
		);
	}
}

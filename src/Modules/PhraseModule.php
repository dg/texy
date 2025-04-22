<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\{Modifier, Patterns, Syntax};
use Texy\Nodes\{ContentNode, InlineCodeNode, LinkNode, PhraseNode, RawTextNode, TextNode};
use Texy\Parsing\ParseContext;


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
				(?<! [^\s.,;:<>()"\'' . Patterns::MARK . '-] )  # must be preceded by these chars
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
				( (?: [^' . Patterns::MARK . '\r\n\']++ | \' )+ )  # content (1)
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
				( \S (?: [^' . Patterns::MARK . '\r\n `]++ | [ `] )* )  # content (1)
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
				( (?: [^' . Patterns::MARK . '|\r\n \]]++ | [ ] )+ )  # link (2)
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
				( (?: [^' . Patterns::MARK . '\r )]++ | [ ] )+ )  # link (3)
				\)
			~Ux',
			$this->parseLink(...),
		);

		// \* escaped asterisk
		$registry->addInline(
			Syntax::EscapedAsterisk,
			'~\\\\\*~',
			fn($context, $matches) => new TextNode('*'),
		);
	}


	/**
	 * Parses phrase patterns. Phrases without a link part (ins, del, sup, sub) have
	 * only two groups, hence the optional third one.
	 * @param  array{string, string, ?string, 3?: ?string}  $matches
	 */
	public function parsePhrase(
		ParseContext $context,
		array $matches,
		string $phrase,
	): PhraseNode|InlineCodeNode|LinkNode|null
	{
		[, $mContent, $mMod, $mLink] = $matches + [3 => null];

		// For phrase/span and phrase/span-alt, URL makes it a link
		if ($phrase === Syntax::SpanQuotes || $phrase === Syntax::SpanTilde) {
			if ($mLink !== null) {
				return new LinkNode(
					url: Texy\Helpers::decodeEntities($mLink),
					content: $context->parseInline(trim($mContent)),
					modifier: Modifier::parse($mMod),
				);

			} elseif ($mMod === null) {
				return null;
			}
		}

		$mod = Modifier::parse($mMod);
		$node = $phrase === Syntax::Code
			? new InlineCodeNode(trim($mContent), $mod) // code content is not parsed recursively
			: new PhraseNode($context->parseInline(trim($mContent)), Syntax::PhraseTypes[$phrase], $mod, $phrase);

		// Other phrases with link
		if ($mLink !== null && $this->config->phraseLinks) {
			return new LinkNode(
				url: Texy\Helpers::decodeEntities($mLink),
				content: new ContentNode([$node]),
			);
		}

		return $node;
	}


	/**
	 * Parses superscript/subscript alternative (m^2, H_2O).
	 * @param  array{string, string}  $matches
	 */
	public function parseSupSub(ParseContext $context, array $matches, string $phrase): PhraseNode
	{
		[, $mContent] = $matches;
		$mContent = str_replace('-', "\u{2212}", $mContent); // &minus;
		return new PhraseNode(
			new ContentNode([new TextNode(Texy\Helpers::decodeEntities($mContent))]),
			Syntax::PhraseTypes[$phrase],
			syntax: $phrase,
		);
	}


	/**
	 * Parses acronym/abbr.
	 * @param  array{string, string, ?string, string}  $matches
	 */
	public function parseAcronym(ParseContext $context, array $matches, string $phrase): PhraseNode
	{
		[, $mContent, $mMod, $mTitle] = $matches;
		$mod = Modifier::parse($mMod) ?? new Modifier;
		$mod->title = trim(Texy\Helpers::unescapeHtml($mTitle));
		return new PhraseNode($context->parseInline(trim($mContent)), Syntax::PhraseTypes[$phrase], $mod, $phrase);
	}


	/**
	 * Parses ''notexy''.
	 * @param  array{string, string}  $matches
	 */
	public function parseNoTexy(ParseContext $context, array $matches): RawTextNode
	{
		[, $mContent] = $matches;
		return new RawTextNode($mContent);
	}


	/**
	 * Parses links (phrase/quicklink, phrase/markdown).
	 * Note: Markdown links don't support modifiers - modifier is intentionally ignored.
	 * @param  array{string, string, ?string, string}  $matches
	 */
	public function parseLink(ParseContext $context, array $matches): LinkNode
	{
		[, $mContent, $mMod, $mLink] = $matches;
		return new LinkNode(
			Texy\Helpers::decodeEntities(trim($mLink)),
			$context->parseInline(trim($mContent)),
			null,  // quicklink and markdown links don't support modifiers
		);
	}


	/**
	 * Parses wikilink [text|link].
	 * Note: Wikilinks don't support modifiers - modifier is intentionally ignored.
	 * @param  array{string, string, string, ?string}  $matches
	 */
	public function parseWikilink(ParseContext $context, array $matches): LinkNode
	{
		[, $mContent, $mLink] = $matches;
		return new LinkNode(
			Texy\Helpers::decodeEntities(trim($mLink)),
			$context->parseInline(trim($mContent)),
			null,  // wikilinks don't support modifiers
		);
	}
}

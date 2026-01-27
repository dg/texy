<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;
use Texy\InlineParser;
use Texy\Modifier;
use Texy\Patterns;
use function htmlspecialchars, str_replace, trim;
use const ENT_NOQUOTES;


/**
 * Processes inline text formatting (bold, italic, code, etc.).
 */
final class PhraseModule extends Texy\Module
{
	/** @var array<string, string> */
	public array $tags = [
		'phrase/strong' => 'strong', // or 'b'
		'phrase/em' => 'em', // or 'i'
		'phrase/em-alt' => 'em',
		'phrase/em-alt2' => 'em',
		'phrase/ins' => 'ins',
		'phrase/del' => 'del',
		'phrase/sup' => 'sup',
		'phrase/sup-alt' => 'sup',
		'phrase/sub' => 'sub',
		'phrase/sub-alt' => 'sub',
		'phrase/span' => 'a',
		'phrase/span-alt' => 'a',
		'phrase/acronym' => 'abbr',
		'phrase/acronym-alt' => 'abbr',
		'phrase/code' => 'code',
		'phrase/quote' => 'q',
		'phrase/quicklink' => 'a',
	];

	public bool $linksAllowed = true;


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed['phrase/ins'] = false;
		$texy->allowed['phrase/del'] = false;
		$texy->allowed['phrase/sup'] = false;
		$texy->allowed['phrase/sub'] = false;
		$texy->addHandler('phrase', $this->solve(...));
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
			'phrase/strong+em',
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
			'phrase/strong',
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
			'phrase/em',
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
			'phrase/em-alt',
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
			'phrase/em-alt2',
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
			'phrase/ins',
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
			'phrase/del',
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
			'phrase/sup',
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
			'phrase/sup-alt',
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
			'phrase/sub',
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
			'phrase/sub-alt',
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
			'phrase/span',
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
			'phrase/span-alt',
		);

		// >>quote
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
			'phrase/quote',
		);

		// acronym/abbr "et al."((and others))
		$texy->registerLinePattern(
			$this->parsePhrase(...),
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
			'phrase/acronym',
		);

		// acronym/abbr NATO((North Atlantic Treaty Organisation))
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! [' . Patterns::CHAR . '] )  # not preceded by char
				( [' . Patterns::CHAR . ']{2,} ) # at least 2 chars (1)
				()                               # modifier placeholder (2)
				\(\(
				( (?: [^\n )]++ | [ )] )+ )      # explanation (3)
				\)\)
			~U',
			'phrase/acronym-alt',
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
			'phrase/notexy',
		);

		// `code`
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				`
				( \S (?: [^' . Patterns::MARK . '\r\n `]++ | [ `] )* )  # content (1)
				' . Patterns::MODIFIER . '?             # modifier (2)
				(?<! \s )                               # not preceded by space
				`
				(?: : (' . Patterns::LINK_URL . ') )??  # optional link (3)
			~U',
			'phrase/code',
		);

		// ....:LINK
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				( [' . Patterns::CHAR . '0-9@#$%&.,_-]++ )  # allowed chars (1)
				()                                    # modifier placeholder (2)
				: (?= \[ )                            # followed by :[
				(' . Patterns::LINK_URL . ')          # link (3)
			~U',
			'phrase/quicklink',
		);

		// [text |link]
		$texy->registerLinePattern(
			$this->parsePhrase(...),
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
			'phrase/wikilink',
		);

		// [text](link)
		$texy->registerLinePattern(
			$this->parsePhrase(...),
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
			'phrase/markdown',
		);

		// \* escaped asterix
		$texy->registerLinePattern(
			fn() => '*',
			'~\\\\\*~',                      // \* -> *
			'phrase/escaped-asterix',
		);
	}


	/**
	 * Parses phrase patterns.
	 * @param  array<?string>  $matches
	 */
	public function parsePhrase(InlineParser $parser, array $matches, string $phrase): Texy\HtmlElement|string|null
	{
		/** @var array{string, string, ?string, ?string} $matches */
		[, $mContent, $mMod, $mLink] = $matches + [3 => null];
		// [1] => ...
		// [2] => .(title)[class]{style}
		// [3] => LINK

		if ($phrase === 'phrase/wikilink') {
			[$mLink, $mMod] = [$mMod, $mLink];
			$mContent = trim($mContent);
		}

		$texy = $this->texy;
		$mod = Modifier::parse($mMod);
		$link = null;

		$parser->again = $phrase !== 'phrase/code' && $phrase !== 'phrase/quicklink';

		if ($phrase === 'phrase/span' || $phrase === 'phrase/span-alt') {
			if ($mLink == null) {
				if (!$mMod) {
					return null; // means "..."
				}
			} else {
				$link = $texy->linkModule->factoryLink($mLink, $mMod, $mContent);
			}
		} elseif ($phrase === 'phrase/acronym' || $phrase === 'phrase/acronym-alt') {
			$mod->title = trim(Texy\Helpers::unescapeHtml((string) $mLink));

		} elseif ($mLink != null) {
			$link = $texy->linkModule->factoryLink($mLink, null, $mContent);
		}

		return $texy->invokeAroundHandlers('phrase', $parser, [$phrase, $mContent, $mod, $link]);
	}


	/**
	 * Parses: any^2 any_2.
	 * @param  array<?string>  $matches
	 */
	public function parseSupSub(InlineParser $parser, array $matches, string $phrase): Texy\HtmlElement|string|null
	{
		/** @var array{string, string} $matches */
		[, $mContent] = $matches;
		$mod = new Modifier;
		$link = null;
		$mContent = str_replace('-', "\u{2212}", $mContent); // &minus;
		return $this->texy->invokeAroundHandlers('phrase', $parser, [$phrase, $mContent, $mod, $link]);
	}


	/** @param  array<?string>  $matches */
	public function parseNoTexy(InlineParser $parser, array $matches): string
	{
		/** @var array{string, string} $matches */
		[, $mContent] = $matches;
		return $this->texy->protect(htmlspecialchars($mContent, ENT_NOQUOTES, 'UTF-8'), Texy\Texy::CONTENT_TEXTUAL);
	}


	/**
	 * Finish invocation.
	 */
	private function solve(
		Texy\HandlerInvocation $invocation,
		string $phrase,
		string $content,
		Modifier $mod,
		?Texy\Link $link = null,
	): Texy\HtmlElement|string|null
	{
		$texy = $this->texy;
		$tag = $this->tags[$phrase] ?? null;

		if ($tag === 'a') {
			$tag = $link && $this->linksAllowed ? null : 'span';
		}

		if ($phrase === 'phrase/code') {
			$content = $texy->protect(htmlspecialchars($content, ENT_NOQUOTES, 'UTF-8'), $texy::CONTENT_TEXTUAL);
		}

		if ($phrase === 'phrase/strong+em') {
			$el = new Texy\HtmlElement($this->tags['phrase/strong']);
			$el->create($this->tags['phrase/em'], $content);
			$mod->decorate($texy, $el);

		} elseif ($tag) {
			$el = new Texy\HtmlElement($tag, $content);
			$mod->decorate($texy, $el);
		} else {
			$el = $content; // trick
		}

		if ($link && $this->linksAllowed) {
			return $texy->linkModule->solve(null, $link, $el);
		}

		return $el;
	}
}
